<?php
namespace TYPO3Incubator\Jobqueue;

use TYPO3Incubator\Jobqueue\Process\ProcessPool;

class PoolWorker
{
    /**
     * @var \Symfony\Component\Process\ProcessBuilder
     */
    protected $processBuilder;
    /**
     * @var ProcessPool
     */
    protected $processPool;

    /**
     * @var Backend\BackendInterface
     */
    private $connection;
    /**
     * @var string
     */
    private $queue;

    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManager
     */
    private $objectManager;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var bool
     */
    protected $running = true;

    /**
     * @var bool
     */
    protected $listening = true;

    /**
     * @var \SplObjectStorage<RunningJob>
     */
    protected $runningJobs;

    /**
     * @var bool
     */
    protected $signalHandlingApplied = false;

    /**
     * @var  bool
     */
    protected $useSignals;

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * PoolWorker constructor.
     * @param \TYPO3Incubator\Jobqueue\Backend\QueueListener $connection
     * @param string $queue
     * @param int $limit
     * @param bool $useSignals
     */
    public function __construct($connection, $queue, $limit, $useSignals = false)
    {
        $this->connection = $connection;
        $this->connection->setMessageLimit($limit);
        $this->queue = $queue;
        $this->objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\ObjectManager::class);
        $this->processPool = $this->objectManager->get(ProcessPool::class, $limit);
        $this->processPool->setProcessFinishedCallback([$this, 'onProcessFinished']);
        $this->useSignals = $useSignals;
        /** @var \TYPO3\CMS\Core\Log\LogManager $logM */
        $logM = $this->objectManager->get(\TYPO3\CMS\Core\Log\LogManager::class);
        $this->logger = $logM->getLogger(__CLASS__);
        $childProcessArgs = [PATH_typo3 . 'sysext/core/bin/typo3', '--mode=p', '--nosig', 'jobqueue:work'];
        if ($this->useSignals === true) {
            unset($childProcessArgs[2]);
        }
        $this->processBuilder = new \Symfony\Component\Process\ProcessBuilder($childProcessArgs);
        $this->runningJobs = new \SplObjectStorage();
    }

    /**
     * @param \Symfony\Component\Process\Process $process
     */
    public function onProcessFinished($process)
    {
        $this->logger->debug('onProcessFinished');
        // find the RunningJob which belongs to this process
        $rj = null;
        foreach ($this->runningJobs as $runningJob) {
            /** @var RunningJob $runningJob */
            if ($runningJob->getProcess() === $process) {
                $this->logger->debug('found the running job for finished process');
                $rj = $runningJob;
                $this->logger->debug('detaching from local storage');
                $this->runningJobs->detach($runningJob);
                break;
            }
        }
        if ($rj instanceof RunningJob) {
            $this->logger->debug('handling output');
            $this->handleProcessOutput($rj->getProcess()->getOutput(), $rj->getMessage());
            unset($rj);
            $this->logger->debug('cleanup done');
            $this->logger->debug('handled messages ' . ++$this->processedJobs);
            return;
        }
    }

    /**
     * @param string $output
     * @param Message $msg
     */
    protected function handleProcessOutput($output, Message $msg)
    {
        $result = json_decode($output, true);
        // invalid output
        if ($result === null) {
            // shutdown initiated
            if ($this->running === false) {
                // we have invalid output because we killed our child process -> simple requeue
                $this->logger->debug('simple requeue. we killed our child for shutdown');
                $this->connection->update($msg);
            } else {
                // we have invalid output because of some other error -> increment attempts and requeu
                $this->logger->debug('invalid output. attempts + 1 and requeue');
                $this->logger->debug('output: ' . var_export($output, true));
                $msg->setAttempts($msg->getAttempts() + 1);
                $this->connection->update($msg);
            }
            return;
        }
        switch ($result['action']) {
            case 'requeue':
                $nextexecution = (!empty($result['nextexecution'])) ? $result['nextexecution'] : time();
                $attempts = (!empty($result['attempts'])) ? $result['attempts'] : time();
                $msg->setNextExecution($nextexecution);
                $msg->setAttempts($attempts);
                if ($msg->getAttempts() >= $this->configuration->getAttemptsLimit()) {
                    $this->connection->failed($msg);
                } else {
                    $this->connection->update($msg);
                }
                break;
            case 'delete':
                $this->logger->debug('action: remove');
                $this->connection->remove($msg);
                break;
            default:
                $this->logger->debug('unknown action');
                $this->logger->debug('output: ' . $output);
                break;
        }
    }

    /**
     * This callback is called when a message has been received by the backend
     * @param Message $msg
     */
    public function onMessageReceived($msg)
    {
        $this->logger->debug('onMessageReived: receveived a new message from the queue');
        // update our process pool
        $this->processPool->externalTick();
        // if we can start a new process we will do so
        if ($this->processPool->canRunProcess()) {
            // build our process
            $process = $this->processBuilder->getProcess();
            // add the data
            $process->setInput(json_encode($msg));
            // create our wrapper for cleanup tracking
            $this->runningJobs->attach(new RunningJob($msg, $process));
            // pass the process to the pool
            $this->processPool->run($process);
            // refresh the pool, maybe a process finished during our setup
            $this->processPool->externalTick();
        }
    }


    /**
     *
     */
    public function run()
    {
        $this->connection->startListening($this->queue, [$this, 'onMessageReceived']);
        while ($this->running) {
            // manually force signal dispatching inside main loop
            if ($this->useSignals === true) {
                pcntl_signal_dispatch();
            }
            $this->processPool->externalTick();
            if ($this->listening) {
                // if our pool is busy atm it makes no sense to wait for a new job.
                if ($this->processPool->canRunProcess()) {
                    // if we can run another process but the pool has running processes
                    // -> we make a non blocking wait call
                    if ($this->processPool->hasRunningProcesses()) {
                        $this->wait();
                    } else {
                        // no actively running processes. make a blocking wait call!
                        $this->wait(true);
                    }
                } else {
                    // pool is busy ... wait a little and let our loop do the job
                    usleep(100000);
                }
            } else {
                // we are not listening atm. probably because our pool is busy.
                usleep(100000);
                $this->processPool->externalTick();
                // go back into listen mode if a process has finished
                if ($this->processPool->canRunProcess()) {
                    $this->listening = true;
                    $this->connection->startListening($this->queue, [$this, 'onMessageReceived']);
                }
            }
        }
    }

    protected function wait($blocking = false)
    {
        $this->connection->wait($blocking);
    }

    public function shutdown($graceful = false)
    {
        $this->logger->debug('entering shutdown. graceful: ' . var_export($graceful, true));
        $this->listening = false;
        $this->running = false;
        if ($graceful === false) {
            // we kill our childs and requeue the currently running jobs
            // initiateShutdown is blocking. Callback for handling output is then processed by ticking the pool
            $this->logger->debug('entering kill mode');
            $this->processPool->initiateShutdown(false);
            while ($this->runningJobs->count() > 0) {
                $this->processPool->externalTick();
            }
            $this->connection->stopListening($this->queue);
            $this->logger->debug('chils killed. exiting...');
            exit();
        } else {
            $this->logger->debug('gracefully waiting for child processes to finish');
            // we simply wait for the current jobs to be finished and then exit
            while ($this->processPool->hasRunningProcesses()) {
                usleep(1000);
                $this->processPool->externalTick();
            }
            $this->connection->stopListening($this->queue);
            $this->logger->debug('child processes done. exiting...');
            exit();
        }
    }

    public function gracefulShutdown()
    {
        $this->shutdown(true);
    }

    /**
     * @param Configuration $configuration
     */
    public function injectConfiguration(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

}