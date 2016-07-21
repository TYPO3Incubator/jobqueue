<?php
namespace TYPO3Incubator\Jobqueue\Command;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListenCommand extends Command
{
    public function configure()
    {
        $this->setDescription('Queue listener');
        $this->addArgument('connection', \Symfony\Component\Console\Input\InputArgument::REQUIRED,
            'Name of the connection to use');
        $this->addArgument('queue', \Symfony\Component\Console\Input\InputArgument::REQUIRED,
            'Name of the queue to work on');
        $this->addOption('limit', 'l', \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED, 'Worker limit', 3);
        $this->addOption('nosig', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE,
            'Disables signal handling');
        $this->addOption('graceful-shutdown', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE,
            'Activating this switch will cause the listener to wait for child processes to finish before exiting');
    }


    public function execute(InputInterface $input, OutputInterface $output)
    {

        $connectionIdetifier = $input->getArgument('connection');
        $queue = $input->getArgument('queue');
        $limit = (int)$input->getOption('limit');
        $disableSignalHandling = $input->getOption('nosig');
        $gracefulShutdown = $input->getOption('graceful-shutdown');
        /** @var \TYPO3Incubator\Jobqueue\QueueManager $queueManager */
        $queueManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3Incubator\Jobqueue\QueueManager::class);

        $connection = $queueManager->getBackend($connectionIdetifier);

        if (!$connection instanceof \TYPO3Incubator\Jobqueue\Backend\QueueListener) {
            $backend = new \TYPO3Incubator\Jobqueue\Backend\FallbackListener($connection);
        } else {
            $backend = $connection;
        }
        $useSignals = !$disableSignalHandling;
        /** @var \TYPO3Incubator\Jobqueue\PoolWorker $poolWorker */
        $poolWorker = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3Incubator\Jobqueue\PoolWorker::class,
            $backend, $queue, $limit, $useSignals);

        if ($disableSignalHandling === false) {

            declare(ticks = 1);
            $method = ($gracefulShutdown === false) ? 'shutdown' : 'gracefulShutdown';
            $callable = [$poolWorker, $method];
            \TYPO3Incubator\Jobqueue\Utility::applySignalHandling([SIGTERM, SIGQUIT, SIGHUP, SIGINT, SIGQUIT],
                function ($sig) use ($callable) {
                    call_user_func($callable);
                });
        }
        $poolWorker->run();
    }


}