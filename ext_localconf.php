<?php
if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
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
            'options' => [
                'table' => 'jobqueue_job'
            ],
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
            'queues' => [
                'failed' => [
                    'auto_delete' => false
                ]
            ],
            'virtualQueues' => [
                'name-of-the-virtual-queue' => [
                    'exchange' => 'name-of-the-exchange-to-use',
                    'routing' => 'routing-key-to-use'
                ]
            ]
        ]
    ],
    'attemptsLimit' => 5
];

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'TYPO3Incubator.Jobqueue',
    'example',
    ['Example' => 'index,queue,info'],
    ['Example' => 'queue,info']
);
