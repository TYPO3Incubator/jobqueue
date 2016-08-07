<?php
namespace TYPO3Incubator\Jobqueue;

class AmqpUtility
{

    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * queue definitions
     * @var array
     */
    protected $queues;

    /**
     * virtual queue definitions
     * @var array
     */
    protected $virtualQueues;

    /**
     * default direct exchange
     * @var string
     */
    protected $defaultExchange = 'typo3.direct';

    /**
     * @var array
     */
    protected $defaultQueueDefinition = [
        'queue' => '',
        'passive' => false,
        'durable' => true,
        'exclusive' => false,
        'auto_delete' => false,
        'arguments' => [
            'x-dead-letter-exchange' => ['S', ''],
            'x-dead-letter-routing-key' => ['S', '']
        ]
    ];

    /**
     * @var array
     */
    protected $defaultExchangeDefintion = [
        'exchange' => '',
        'type' => 'direct',
        'passive' => false,
        'durable' => true,
        'auto_delete' => false,
        'internal' => false,
        'arguments' => null
    ];

    /**
     * AmqpUtility constructor.
     * @param string $identifier
     */
    public function __construct($identifier)
    {
        $this->identifier = $identifier;
    }

    /**
     * Initial setup
     * @throws \InvalidArgumentException
     */
    public function initializeObject()
    {
        $config = $this->configuration->getBackendConfiguration($this->identifier);
        if (!is_array($config) || $config['backend'] !== \TYPO3Incubator\Jobqueue\Backend\AmqpBackend::class) {
            throw new \InvalidArgumentException("'{$this->identifier}' has no configuration or does not reference an AmqpBackend!");
        }

        $this->queues = (!empty($config['queues'])) ? $config['queues'] : [];
        $this->virtualQueues = (!empty($config['virtualQueues'])) ? $config['virtualQueues'] : [];

        $this->defaultQueueDefinition['arguments']['x-dead-letter-exchange'][1] = $this->defaultExchange;
        $this->defaultQueueDefinition['arguments']['x-dead-letter-routing-key'][1] = $this->getDefaultRoutingKey('failed');

        $this->defaultExchangeDefintion['exchange'] = $this->defaultExchange;

    }

    /**
     * @param $queue
     * @return array|mixed
     */
    public function getQueueDefinition($queue)
    {
        if ($this->queueOverrideExists($queue)) {
            $this->queues[$queue] = array_merge($this->defaultQueueDefinition, $this->queues[$queue], ['queue' => $queue]);
            return $this->queues[$queue];
        }
        return array_merge($this->defaultQueueDefinition, ['queue' => $queue]);
    }

    public function getDefaultExchangeDefinition()
    {
        return $this->defaultExchangeDefintion;
    }

    /**
     * @param $queue
     * @return array 0 -> exchange, 1 -> routing key
     */
    public function getPublishInformation($queue)
    {
        $exchange = '';
        $routing = '';
        if ($this->isVirtualQueue($queue)) {
            $exchange = $this->virtualQueues[$queue]['exchange'];
            $routing = $this->virtualQueues[$queue]['routing'];
        } else {
            $exchange = $this->defaultExchange;
            $routing = $this->getDefaultRoutingKey($queue);
        }
        return array($exchange, $routing);
    }

    /**
     * @param string $queue
     * @return string
     */
    protected function getDefaultRoutingKey($queue)
    {
        return $queue . '-direct';
    }


    /**
     * @param string $queue
     * @return bool
     */
    protected function queueOverrideExists($queue)
    {
        return isset($this->queues[$queue]);
    }

    /**
     * @param string $queue
     * @return bool
     */
    public function isVirtualQueue($queue)
    {
        return isset($this->virtualQueues[$queue]);
    }


    /**
     * @param Configuration $configuration
     */
    public function injectConfiguration(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

}