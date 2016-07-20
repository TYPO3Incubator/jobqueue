<?php
if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
}

if (TYPO3_MODE === 'BE') {

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['cliKeys']['jobqueue:work'] = array(
        function() {
            $cliController = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3Incubator\Jobqueue\Cli\WorkController::class);
            $cliController->run();
        },
        '_CLI_lowlevel'
    );

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['cliKeys']['sleep'] = array(
        function() {
            $logM = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class);
            /** @var \Psr\Log\LoggerInterface $logger */
            $logger = $logM->getLogger(\TYPO3Incubator\Jobqueue\QueueManager::class);
            declare(ticks=1) {
                $cb = function($signo) use ($logger) {
                    $logger->debug('from '.posix_getpid().': got signal: '.$signo);
                    if($signo == 30) {
                        fwrite(STDERR,'aborting');
                        exit();
                    }
                };
                pcntl_signal(30, $cb, false);
                pcntl_signal(SIGINT, $cb, false);
                pcntl_signal(SIGUSR1, $cb, false);
                pcntl_signal(SIGTERM, $cb, false);
                $json = fgets(STDIN);
                $data = json_decode($json);
                $duration = (int) $data->duration;
                #$logger->debug("sleeping for $duration seconds ", ['set' => $set]);
                usleep(1000000);usleep(1000000);usleep(1000000);usleep(1000000);usleep(1000000);usleep(1000000);usleep(1000000);usleep(1000000);usleep(1000000);usleep(1000000);
                fwrite(STDOUT, 'done');
            }
        },
        '_CLI_lowlevel'
    );

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['cliKeys']['jobqueue:listen'] = array(
        function() {
            #`export XDEBUG_CONFIG=""`;
            $cliController = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3Incubator\Jobqueue\Cli\ListenController::class);
            $cliController->run();
        },
        '_CLI_lowlevel'
    );

}

/**
 * Temp Debugging Log Configuration
 */
$GLOBALS['TYPO3_CONF_VARS']['LOG']['TYPO3Incubator'] = [
    'writerConfiguration' => [
        \TYPO3\CMS\Core\Log\LogLevel::DEBUG => [
            \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
                'logFile' => 'typo3temp/var/logs/typo3incubator.log'
            ]
        ]
    ],
    'processorConfiguration' => [
        \TYPO3\CMS\Core\Log\LogLevel::DEBUG => [
            \TYPO3\CMS\Core\Log\Processor\MemoryUsageProcessor::class => [
                'realMemoryUsage' => true,
                'formatSize' => true
            ]
        ]
    ]
];

/**
 * Default Configuration
 */
$GLOBALS['TYPO3_CONF_VARS']['SYS']['queue'] = [
    'configuration' => [
        'default' => [
            'backend' => \TYPO3Incubator\Jobqueue\Backend\DatabaseBackend::class,
            'options' => [],
            'defaultQueue' => 'default'
        ],
        'rabbitmq' => [
            'backend' => \TYPO3Incubator\Jobqueue\Backend\AmqpBackend::class,
            'options' => [
                'host' => 'localhost',
                'port' => 5672,
                'user' => 'guest',
                'password' => 'guest',
                'vhost' => '/',
                /*'ssl' => [
                    'cafile' => '/some/path/cacert.pem',
                    'local_cert' => '/some/path/phpcert.pem',
                    'verify_peer' => true
                ]*/
            ],
            'defaultQueue' => 'default',
            'exchanges' => [],
            'queues' => [
                'name-of-the-virtual-queue' => [
                    'exchange' => 'name-of-the-exchange-to-use',
                    'routing' => 'routing-key-to-use'
                ]
            ]
        ]
    ]
];


\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'TYPO3Incubator.Jobqueue',
    'example',
    ['Example' => 'index,queue,info'],
    ['Example' => 'queue,info']
);
