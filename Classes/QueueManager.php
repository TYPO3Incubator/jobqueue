<?php
namespace TYPO3Incubator\Jobqueue;

use TYPO3\CMS\Core\SingletonInterface;

class QueueManager implements SingletonInterface
{

    /**
     * @var array
     */
    protected $initializedBackends = [];

    /**
     * @param $identifier
     * @return \TYPO3Incubator\Jobqueue\Frontend\Queue
     * @throws \TYPO3\CMS\Core\Resource\Exception\InvalidConfigurationException
     * @throws \RuntimeException
     */
    public function get($identifier) 
    {
        if($this->isQueueDefined($identifier) === false) {
            throw new \InvalidArgumentException("No queue configuration set for '{$identifier}'");
        }
        if(! isset($this->initializedBackends[$identifier])) {
            $conf = &$GLOBALS['TYPO3_CONF_VARS']['SYS']['queue']['configuration'][$identifier];
            $backend = $conf['backend'];
            $options = $conf['options'];
            $defaultQueue = $conf['defaultQeueue'];
            if(!class_exists($backend)) {
                throw new \TYPO3\CMS\Core\Resource\Exception\InvalidConfigurationException("The configured backend class '{$backend}' does not exist!");
            }
            try {
                $options = array_merge($options, ['identifier' => $identifier]);
                $this->initializedBackends[$identifier] = new $backend($options);
            } catch (\Exception $e) {
                $msg = $e->getMessage();
                throw new \RuntimeException("The queue could not be initialized '{$msg}'");
            }
        }
        // @todo defaultQeueue ...
        return new \TYPO3Incubator\Jobqueue\Frontend\Queue($this->initializedBackends[$identifier], 'default');
    }

    /**
     * @param $identifier
     * @return \TYPO3Incubator\Jobqueue\Backend\BackendInterface
     * @throws \TYPO3\CMS\Core\Resource\Exception\InvalidConfigurationException
     */
    public function getBackend($identifier)
    {
        // @todo refactor!
        $this->get($identifier);
        return $this->initializedBackends[$identifier];
    }

    /**
     * @param string $name
     * @return bool
     */
    public function isQueueDefined($name)
    {
        return isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['queue']['configuration'][$name]);
    }

}
