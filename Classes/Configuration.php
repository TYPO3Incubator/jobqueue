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
     * @param string $identifier
     * @return array|null
     */
    public function getBackendConfiguration($identifier)
    {
        return (isset($this->config['configuration'][$identifier])) ? $this->config['configuration'][$identifier] : null;
    }

    /**
     * @return int
     */
    public function getAttemptsLimit()
    {
        return $this->config['attemptsLimit'];
    }

}