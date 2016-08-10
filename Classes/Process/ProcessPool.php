<?php
namespace TYPO3Incubator\Jobqueue\Process;


class ProcessPool
{
    /**
     * @var \SplObjectStorage<\Symfony\Component\Process\Process>
     */
    protected $runningProcesses;

    /**
     * @var \SplObjectStorage<\Symfony\Component\Process\Process>
     */
    protected $queuedProcesses;

    /**
     * @var int
     */
    protected $limit;

    /**
     * boolean indicating if this process pool should be running
     * @var bool
     */
    protected $running;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;
    /**
     * @var bool
     */
    private $queueing;

    /**
     * @var callable
     */
    private $processDoneCallback;

    /**
     * ProcessPool constructor.
     * @param int $limit
     * @param bool $queueing
     */
    public function __construct($limit, $queueing = false)
    {
        $this->runningProcesses = new \SplObjectStorage();
        $this->queuedProcesses = new \SplObjectStorage();
        $this->limit = (int)$limit;
        $this->queueing = $queueing;
        $this->running = true;
        $logM = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class);
        $this->logger = $logM->getLogger(\TYPO3Incubator\Jobqueue\ProcessPool::class);
        #$this->pb = new \Symfony\Component\Process\ProcessBuilder(['/Users/dustin/Development/vhosts/typo3-jobqueue.local/web/typo3/cli_dispatch.phpsh', 'sleep']);
    }

    /**
     * @param callable $cb
     */
    public function setProcessFinishedCallback($cb)
    {
        $this->processDoneCallback = $cb;
    }

    public function isRunning()
    {
        return $this->running;
    }

    public function run(\Symfony\Component\Process\Process $process)
    {
        if ($this->running) {
            $this->updateRunningProcesses();
            if ($this->canRunProcess()) {
                $this->logger->debug('can run process directly');
                $this->runningProcesses->attach($process);
                $process->start();
            } else {
                if ($this->queueing === true) {
                    $this->logger->debug('pool busy ... queueing process');
                    $this->queuedProcesses->attach($process);
                } else {
                    throw new \RuntimeException('pool busy. process queueing disabled');
                }
            }
        }
    }

    /**
     *
     */
    public function externalTick()
    {
        #$this->logger->debug('external tick received');
        $this->updateRunningProcesses();
        if ($this->running === false) {
            return;
        }
        if (!$this->queueing) {
            return;
        }
        while ($this->canRunProcess() && $this->hasQueuedProcesses()) {
            #$this->logger->debug('running queued process');
            // make sure we are at the beginning
            $this->queuedProcesses->rewind();
            /** @var \Symfony\Component\Process\Process $process */
            $process = $this->queuedProcesses->current();
            $this->queuedProcesses->detach($process);
            $this->runningProcesses->attach($process);
            $process->start();
        }
        #$this->logger->debug('runningProcesses', ['count' => $this->runningProcesses->count()]);
        #$this->logger->debug('queuedProcesses', ['count' => $this->queuedProcesses->count()]);
    }

    /**
     * debug
     * @return array
     */
    public function getRunningProcessesInfo()
    {
        $info = [];
        foreach ($this->runningProcesses as $k => $p) {
            $info[] = [
                'pid' => $p->getPid(),
                'status' => $p->getStatus()
            ];
        }
        return $info;
    }

    public function hasRunningProcesses()
    {
        $this->updateRunningProcesses();
        return ($this->runningProcesses->count() > 0);
    }

    public function hasQueuedProcesses()
    {
        return ($this->queuedProcesses->count() > 0);
    }

    public function initiateShutdown($graceful = true)
    {
        $this->logger->debug('shutdown initiated!');
        $this->running = false;
        if ($graceful === true) {
            $this->logger->debug('gracefully sending signal to child processes');
            foreach ($this->runningProcesses as $process) {
                /** @var $process \Symfony\Component\Process\Process */
                if ($process->isRunning()) {
                    $pid = $process->getPid();
                    $this->logger->debug($pid . ' sending SIGUSR1');
                    #$process->stop(2, SIGUSR1);
                    $process->signal(30);
                }
            }
        } else {
            $this->logger->debug('killing childs');
            foreach ($this->runningProcesses as $process) {
                /** @var $process \Symfony\Component\Process\Process */
                $process->stop(2);
            }
        }
    }

    public function canRunProcess()
    {
        return $this->runningProcesses->count() < $this->limit;
    }

    protected function updateRunningProcesses()
    {
        foreach ($this->runningProcesses as $process) {
            /** @var $process \Symfony\Component\Process\Process */
            if (!$process->isRunning()) {
                #$this->logger->debug('detaching finished process', array('stdout' => $process->getOutput(), 'stderr' => $process->getErrorOutput()));
                if (is_callable($this->processDoneCallback)) {
                    call_user_func($this->processDoneCallback, $process);
                }
                $this->runningProcesses->detach($process);
            }
        }
    }

}
