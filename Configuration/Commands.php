<?php
/**
 * Commands to be executed by typo3, where the key of the array
 * is the name of the command (to be called as the first argument after typo3).
 * Required parameter is the "class" of the command which needs to be a subclass
 * of Symfony/Console/Command. An optional parameter is "user" that logs in
 * a Backend user via CLI.
 *
 * example: bin/typo3 backend:lock
 */
return [
    'jobqueue:listen' => [
        'class' => \TYPO3Incubator\Jobqueue\Command\ListenCommand::class,
        'user' => '_CLI_lowlevel'
    ],
    'jobqueue:work' => [
        'class' => \TYPO3Incubator\Jobqueue\Command\WorkCommand::class,
        'user' => '_CLI_lowlevel'
    ]
];
