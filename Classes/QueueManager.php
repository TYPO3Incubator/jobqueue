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
     * @var array
     */
    protected $backendConfigs;

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManager
     */
    protected $objectManager;

    /**
     * @param $identifier
     * @return \TYPO3Incubator\Jobqueue\Frontend\Queue
     * @throws \TYPO3\CMS\Core\Resource\Exception\InvalidConfigurationException
     * @throws \RuntimeException
     */
    public function get($identifier)
    {
        $backend = $this->getBackend($identifier);
        $config = $this->getBackendConfiguration($identifier);
        $defaultQueue = (empty($config['defaultQueue'])) ? 'default' : $config['defaultQueue'];
        return new \TYPO3Incubator\Jobqueue\Frontend\Queue($backend, $defaultQueue);
    }

    /**
     * @param $identifier
     * @return \TYPO3Incubator\Jobqueue\Backend\BackendInterface
     * @throws \TYPO3\CMS\Core\Resource\Exception\InvalidConfigurationException
     */
    public function getBackend($identifier)
    {
        if ($this->isBackendDefined($identifier) === false) {
            throw new \InvalidArgumentException("No backend configuration set for '{$identifier}'");
        }
        if (!isset($this->initializedBackends[$identifier])) {
            $conf = $this->getBackendConfiguration($identifier);
            $backend = $conf['backend'];
            $options = $conf['options'];
            if (!class_exists($backend)) {
                throw new \TYPO3\CMS\Core\Resource\Exception\InvalidConfigurationException("The configured backend class '{$backend}' does not exist!");
            }
            try {
                $options = array_merge($options, ['identifier' => $identifier]);
                $this->initializedBackends[$identifier] = $this->objectManager->get($backend, $options);;
            } catch (\Exception $e) {
                $msg = $e->getMessage();
                throw new \RuntimeException("The queue could not be initialized '{$msg}'");
            }
        }
        return $this->initializedBackends[$identifier];
    }

    /**
     * @param $identifier
     * @return array
     */
    protected function getBackendConfiguration($identifier)
    {
        return $this->backendConfigs[$identifier];
    }

    /**
     * @param string $name
     * @return bool
     */
    protected function isBackendDefined($name)
    {
        if($this->backendConfigs === null) {
            $this->backendConfigs = $this->configuration->getBackends();
        }
        return isset($this->backendConfigs[$name]);
    }


    /**
     * @param Configuration $configuration
     */
    public function injectConfiguration(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Object\ObjectManager $objectManager
     */
    public function injectObjectManager(\TYPO3\CMS\Extbase\Object\ObjectManager $objectManager)
    {
        $this->objectManager = $objectManager;
    }

}
