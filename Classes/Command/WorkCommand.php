<?php
namespace TYPO3Incubator\Jobqueue\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WorkCommand extends Command
{

    const MODE_STANDALONE = 's';
    const MODE_PIPED = 'p';

    /**
     * @var string
     */
    protected $mode;

    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManager
     */
    protected $objM;

    /**
     * @var  \TYPO3Incubator\Jobqueue\QueueManager
     */
    protected $queueManager;

    /**
     * @var \TYPO3Incubator\Jobqueue\Backend\BackendInterface
     */
    protected $connection;

    /**
     * @var \TYPO3Incubator\Jobqueue\Configuration
     */
    protected $configuration;

    /**
     * @var string
     */
    protected $queue;

    /**
     * @var string
     */
    protected $connectionArgument;

    protected function configure()
    {
        $this->setDescription('Queue worker');
        $this->addArgument('connection', \Symfony\Component\Console\Input\InputArgument::OPTIONAL,
            'Name of the connection to use');
        $this->addArgument('queue', \Symfony\Component\Console\Input\InputArgument::OPTIONAL,
            'Name of the queue to work on');
        $this->addOption('mode', null, \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED,
            'worker mode. s for standalone and p for piped mode');
        $this->addOption('nosig', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE,
            'Disables signal handling');
        $this->addOption('num', null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL,
            'Number of jobs to process if run in standalone mode', 1);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {

        $this->mode = $input->getOption('mode');
        $availableModes = [self::MODE_PIPED, self::MODE_STANDALONE];

        if (!in_array($this->mode, $availableModes)) {
            throw new \InvalidArgumentException('Worker mode must be one of ' . implode(', ',
                    $availableModes) . '. ' . $this->mode . ' given.');
        }
        $this->queue = $input->getArgument('queue');
        $this->connectionArgument = $input->getArgument('connection');
        $nosig = $input->getOption('nosig');

        if ($nosig === false) {
            declare(ticks = 1);
            \TYPO3Incubator\Jobqueue\Utility::applySignalHandling([SIGTERM, SIGQUIT, SIGHUP, SIGINT, SIGQUIT],
                function ($sig) {
                });
        }

        $this->objM = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\ObjectManager::class);

        if ($this->mode === self::MODE_STANDALONE) {
            $this->queueManager = $this->objM->get(\TYPO3Incubator\Jobqueue\QueueManager::class);
            $this->connection = $this->queueManager->getBackend($this->connectionArgument);
            $this->configuration = $this->objM->get(\TYPO3Incubator\Jobqueue\Configuration::class);
            $num = $input->getOption('num');
            $this->runStandalone($num);
            exit;
        } else {
            $result = $this->runPiped();
            if (is_string($result)) {
                $output->write($result);
                exit;
            }
        }
    }

    /**
     * @param $message
     * @return \TYPO3Incubator\Jobqueue\Job
     * @throws \TYPO3Incubator\Jobqueue\ProcessingException
     */
    protected function processMessage($message)
    {
        /** @var \TYPO3Incubator\Jobqueue\Worker $worker */
        $worker = $this->objM->get(\TYPO3Incubator\Jobqueue\Worker::class, $message);
        $job = $worker->run();
        return $job;
    }

    protected function runStandalone($num)
    {
        for($i = 0; $i < $num; $i++) {
            $message = $this->connection->get($this->queue);
            if ($message instanceof \TYPO3Incubator\Jobqueue\Message) {
                try {
                    $job = $this->processMessage($message);
                } catch (\TYPO3Incubator\Jobqueue\ProcessingException $e) {
                    // in case of an exception attempts already have been updated
                    if($message->getAttempts() >= $this->configuration->getAttemptsLimit()) {
                        $this->connection->failed($message);
                        return;
                    } else {
                        $this->connection->update($message);
                        return;
                    }
                }
                if ($job->isReleased()) {
                    $this->connection->update($message);
                }
                if ($job->isDeleted()) {
                    $this->connection->remove($message);
                }
            }
        }
    }

    protected function runPiped()
    {
        $message = \TYPO3Incubator\Jobqueue\Utility::parseMessage();
        if ($message instanceof \TYPO3Incubator\Jobqueue\Message) {
            $result = [
                'action' => ''
            ];
            try {
                $job = $this->processMessage($message);
            } catch (\TYPO3Incubator\Jobqueue\ProcessingException $e) {
                // let our listener decide what to do next
                $result['action'] = 'requeue';
                $result['nextexecution'] = $message->getNextExecution();
                $result['attempts'] = $message->getAttempts();
            }
            if (isset($job) && $job instanceof \TYPO3Incubator\Jobqueue\Job) {
                if ($job->isReleased()) {
                    $result['action'] = 'requeue';
                    $result['nextexecution'] = $message->getNextExecution();
                    $result['attempts'] = $message->getAttempts();
                }
                if ($job->isDeleted()) {
                    $result['action'] = 'delete';
                }
            }
            return json_encode($result);
        }
        return null;
    }

    /**
     * @param array $data
     * @param OutputInterface $output
     */
    protected function sendResultAndExit(array $data, OutputInterface $output)
    {
        $output->write(json_encode($data));
        exit;
    }

}