<?php
namespace TYPO3Incubator\Jobqueue\Backend;

use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use TYPO3\CMS\Core\Core\Bootstrap;

class AmqpBackend implements BackendInterface, QueueListener
{
    /**
     * @var AMQPStreamConnection|AMQPSSLConnection
     */
    protected $connection;

    /**
     * identifier for this backend
     * @var string
     */
    protected $identifier;

    /**
     * @var bool
     */
    protected $defaultExchangeDeclared = false;

    /**
     * array holding
     * @var array
     */
    protected $declaredExchanges = [];

    /**
     * array holding already declared queues
     * @var array
     */
    protected $declaredQueues = [];

    /**
     * @var array
     */
    protected $boundQueues = [];
    /**
     * @var \PhpAmqpLib\Channel\AMQPChannel
     */
    protected $publishChannel;

    /**
     * @var \PhpAmqpLib\Channel\AMQPChannel
     */
    protected $consumeChannel;

    /**
     * @var callable
     */
    protected $listenCallback;

    /**
     * @var string
     */
    protected $listenQueue;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \TYPO3Incubator\Jobqueue\AmqpUtility
     */
    protected $amqpUtility;

    public function __construct($options)
    {

        if (!isset($options['ssl'])) {
            $this->connection = new AMQPStreamConnection($options['host'], $options['port'], $options['user'],
                $options['password'], $options['vhost']);
        } else {
            $this->connection = new AMQPSSLConnection($options['host'], $options['port'], $options['user'],
                $options['password'], $options['vhost'], $options['ssl']);
        }
        $this->identifier = $options['identifier'];
        $this->publishChannel = $this->connection->channel();
        $this->publishChannel->confirm_select();
        $this->consumeChannel = $this->connection->channel();
        /** @var \TYPO3\CMS\Extbase\Object\ObjectManager $objM */
        $objM = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\ObjectManager::class);
        $this->amqpUtility = $objM->get(\TYPO3Incubator\Jobqueue\AmqpUtility::class, $this->identifier);
        /** @var \TYPO3\CMS\Core\Log\LogManager $logManager */
        $logManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class);
        /** @var \Psr\Log\LoggerInterface $logger */
        $this->logger = $logManager->getLogger(__CLASS__);
    }

    /**
     * @param string $queue
     * @param string $handler
     * @param array $data
     */
    public function queue($queue, $handler, $data)
    {
        $message = new \TYPO3Incubator\Jobqueue\Message($handler, $data, []);
        $msg = new \PhpAmqpLib\Message\AMQPMessage(json_encode($message),
            ['delivery_mode' => \PhpAmqpLib\Message\AMQPMessage::DELIVERY_MODE_PERSISTENT]);
        list($exchange, $routing) = $this->getPublishInformation($queue);
        $this->publishChannel->basic_publish($msg, $exchange, $routing);
        $this->publishChannel->wait_for_pending_acks();
    }

    /**
     * @param string $queue
     * @param bool $lock
     * @return null|\TYPO3Incubator\Jobqueue\Message
     */
    public function get($queue, $lock = true)
    {
        $this->getPublishInformation($queue);
        $message = $this->consumeChannel->basic_get($queue, false);
        if ($message instanceof \PhpAmqpLib\Message\AMQPMessage) {
            $msg = $this->buildMessage($message);
            if ($msg instanceof \TYPO3Incubator\Jobqueue\Message) {
                $msg->setMeta('amqp.queue', $queue);
                /*
                if lock = false the message should be ready again right away. therefore we simpyl nack it
                */
                if($lock === false) {
                    $deliveryTag = $msg->getMeta('amqp.delivery_tag', null);
                    $this->consumeChannel->basic_nack($deliveryTag, false, true);
                }
                return $msg;
            }
        }
        return null;
    }


    /**
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     */
    public function onMessageReceived(\PhpAmqpLib\Message\AMQPMessage $message)
    {
        $msg = $this->buildMessage($message);
        if ($msg instanceof \TYPO3Incubator\Jobqueue\Message) {
            $msg->setMeta('amqp.queue', $this->listenQueue);
            call_user_func($this->listenCallback, $msg);
        }
    }

    /**
     * @param string $queue
     * @param $cb
     * @return mixed
     */
    public function startListening($queue, $cb)
    {
        if ($this->listenCallback === null || $this->listenQueue === null) {
            $this->listenCallback = $cb;
            $this->listenQueue = $queue;
            $this->declareExchangeIfNeeded();
            $this->declareQueueIfNeeded($queue);
            $this->logger->debug('basic_consume ' . $this->getConsumerTag($queue));
            $this->consumeChannel->basic_consume($queue, $this->getConsumerTag($queue), false, false, false, false,
                array($this, 'onMessageReceived'));
        }
    }

    protected function getConsumerTag($queue)
    {
        return 'typo3.consumer.' . $queue . '.' . Bootstrap::getInstance()->getRequestId();
    }

    /**
     * @param string $queue
     * @return mixed
     */
    public function stopListening($queue)
    {
        /** @var \Psr\Log\LoggerInterface $logger */
        $this->logger->debug('basic_cancel ' . $this->getConsumerTag($queue));
        $this->consumeChannel->basic_cancel($this->getConsumerTag($queue), false, true);
    }


    /**
     * @param bool $blocking
     * @param null $callable
     */
    public function wait($blocking = false, $callable = null)
    {
        $this->consumeChannel->wait(null, true);
    }

    /**
     * @param int $limit
     * @return void
     */
    public function setMessageLimit($limit)
    {
        $this->consumeChannel->basic_qos(0, $limit, true);
    }


    /**
     * @param string $queue
     * @param \TYPO3Incubator\Jobqueue\Message $message
     * @return mixed
     */
    public function add($queue, $message)
    {
        $this->logger->debug('add called', ['msg' => $message, 'queue' => $queue]);
        list($exchange, $routing) = $this->getPublishInformation($queue);
        $msg = $this->buildAMQPMessage($message);
        $this->publishChannel->basic_publish($msg, $exchange, $routing);
        $this->publishChannel->wait_for_pending_acks_returns();
    }

    /**
     * @param \TYPO3Incubator\Jobqueue\Message $message
     * @return mixed
     */
    public function remove($message)
    {
        $this->logger->debug('remove called', ['msg' => $message]);
        // we simply ack the message retrieval and the message will be removed from the queue
        $deliveryTag = $message->getMeta('amqp.delivery_tag', null);
        if ($deliveryTag !== null) {
            $channel = $message->getMeta('amqp.channel', $this->consumeChannel);
            $channel->basic_ack($deliveryTag);
        }
    }

    /**
     * @param \TYPO3Incubator\Jobqueue\Message $message
     * @return mixed
     */
    public function update($message)
    {
        // updating means ack the messsage and queue a new one -> so first remove it (by calling remove which will ack...) and then add a new message
        $this->logger->debug('update called!', ['msg' => $message]);
        $this->remove($message);
        $this->add($message->getMeta('amqp.queue'), $message);
    }


    /**
     * @param string $queue
     * @return int
     */
    public function count($queue)
    {
        $this->declareQueueIfNeeded($queue);
        $queueDef = $this->amqpUtility->getQueueDefinition($queue);
        $queueDef['queue'] = $queue;
        $result = $this->consumeChannel->queue_declare(
            $queueDef['queue'],
            true,
            $queueDef['durable'],
            $queueDef['exclusive'],
            $queueDef['auto_delete'],
            false,
            $queueDef['arguments']
            );
        if (is_array($result)) {
            return (int)$result[1];
        }
    }

    /**
     * @param \TYPO3Incubator\Jobqueue\Message $message
     * @return mixed
     */
    public function failed($message)
    {
        $deliveryTag = $message->getMeta('amqp.delivery_tag', null);
        if($deliveryTag === null) {
            throw new \LogicException('Looks like you are trying to dead letter an unlocked/non-exclusive message');
        }
        $this->declareQueueIfNeeded('failed');
        list($exchange, $routing) = $this->getPublishInformation('failed');
        $this->bindQueue('failed', $exchange, $routing);
        $channel = $message->getMeta('amqp.channel', $this->consumeChannel);
        $channel->basic_reject($deliveryTag, false);
    }

    /*
    |--------------------------------------------------------------------------
    | AMQO Specific Helper Functions
    |--------------------------------------------------------------------------
    */

    /**
     * @param $exchange
     * @param $type
     * @param bool $passive
     * @param bool $durable
     * @param bool $auto_delete
     * @param bool $internal
     * @param null $arguments
     */
    protected function declareExchange(
        $exchange,
        $type,
        $passive = false,
        $durable = false,
        $auto_delete = false,
        $internal = false,
        $arguments = null
    ) {

        $this->publishChannel->exchange_declare($exchange, $type, $passive, $durable, $auto_delete, $internal, true,
            $arguments, null);
        $this->consumeChannel->exchange_declare($exchange, $type, $passive, $durable, $auto_delete, $internal, true,
            $arguments, null);
    }

    /**
     * @param $queue
     * @return array 0 -> exchange, 1 -> routing key
     */
    protected function getPublishInformation($queue)
    {
        $info = $this->amqpUtility->getPublishInformation($queue);
        if (!$this->amqpUtility->isVirtualQueue($queue)) {
            $this->declareExchangeIfNeeded();
            $this->declareQueueIfNeeded($queue);
            $this->bindQueue($queue, $info[0], $info[1]);
        }
        return $info;
    }

    /**
     * Declares the default exchange once
     */
    protected function declareExchangeIfNeeded()
    {
        if ($this->defaultExchangeDeclared === false) {
            // if this is not a virtual queue, we need to setup everything on our own
            $exchangeConfig = $this->amqpUtility->getDefaultExchangeDefinition();
            // make sure our default exchange is declared
            call_user_func_array([$this, 'declareExchange'], $exchangeConfig);
            $this->defaultExchangeDeclared = true;
        }
    }

    /**
     * Gets the correct queue definition and declares the queue if
     * the queue was not already declared
     * @param $queue
     */
    protected function declareQueueIfNeeded($queue)
    {
        if (!isset($this->declaredQueues[$queue])) {
            $queueConfig = $this->amqpUtility->getQueueDefinition($queue);
            $queueConfig['queue'] = $queue;
            call_user_func_array([$this, 'declareQueue'], $queueConfig);
            if (!$this->amqpUtility->isVirtualQueue($queue)) {
                list($exchange, $routing) = $this->amqpUtility->getPublishInformation($queue);
                $this->declareExchangeIfNeeded();
                $this->bindQueue($queue, $exchange, $routing);
            }
            $this->declaredQueues[$queue] = true;
        }
    }

    /**
     * @param string $queue
     * @param bool $passive
     * @param bool $durable
     * @param bool $exclusive
     * @param bool $auto_delete
     * @param null|array $arguments
     */
    protected function declareQueue($queue, $passive, $durable, $exclusive, $auto_delete, $arguments = null)
    {
        $this->publishChannel->queue_declare($queue, $passive, $durable, $exclusive, $auto_delete, true, $arguments, null);
        $this->consumeChannel->queue_declare($queue, $passive, $durable, $exclusive, $auto_delete, true, $arguments, null);
    }

    /**
     * @param string $queue
     * @param string $exchange
     * @param string $key
     */
    protected function bindQueue($queue, $exchange, $key)
    {
        $hash = md5($queue . $exchange . $key);
        if (!isset($this->boundQueues[$hash])) {
            $this->publishChannel->queue_bind($queue, $exchange, $key);
            $this->consumeChannel->queue_bind($queue, $exchange, $key);
            $this->boundQueues[$hash] = true;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Transformation Helper
    |--------------------------------------------------------------------------
    */

    /**
     * @param \TYPO3Incubator\Jobqueue\Message $message
     * @return \PhpAmqpLib\Message\AMQPMessage
     */
    protected function buildAMQPMessage(\TYPO3Incubator\Jobqueue\Message $message)
    {
        return new \PhpAmqpLib\Message\AMQPMessage(json_encode($message), [
            'content_type' => 'application/json',
            'delivery_mode' => \PhpAmqpLib\Message\AMQPMessage::DELIVERY_MODE_PERSISTENT
        ]);
    }

    /**
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     * @return \TYPO3Incubator\Jobqueue\Message
     */
    protected function buildMessage(\PhpAmqpLib\Message\AMQPMessage $message)
    {
        $payload = $message->body;
        while (!is_array($payload) && !is_null($payload)) {
            $payload = json_decode($payload, true);
        }
        $msg = (new \TYPO3Incubator\Jobqueue\Message($payload['handler'], $payload['data']))
            ->setAttempts($payload['attempts'])
            ->setNextExecution($payload['nextexecution']);
        if (!empty($message->delivery_info['delivery_tag'])) {
            $msg->setMeta('amqp.delivery_tag', $message->delivery_info['delivery_tag']);
        }
        if (!empty($message->delivery_info['channel'])) {
            $msg->setMeta('amqp.channel', $message->delivery_info['channel']);
        }
        return $msg;
    }

}
