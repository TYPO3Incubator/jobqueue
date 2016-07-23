<?php

namespace TYPO3Incubator\Jobqueue;

use TYPO3\CMS\Core\SingletonInterface;

class Configuration implements SingletonInterface
{

    /**
     * @var array
     */
    protected $config;

    /**
     * Configuration constructor.
     */
    public function __construct()
    {
        $this->config = &$GLOBALS['TYPO3_CONF_VARS']['SYS']['queue'];
    }

    /**
     * @return array
     */
    public function getBackends()
    {
        return $this->config['configuration'];
    }

    /**
     * @return int
     */
    public function getAttemptsLimit()
    {
        return $this->config['attemptsLimit'];
    }

}