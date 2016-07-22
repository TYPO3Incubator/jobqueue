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
     * cached information for routing
     * @var array
     */
    protected $exchangeRouting = [];

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
    protected $channel;

    /**
     * @var string
     */
    protected $defaultExchange = 'typo3.direct';

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
     * @var bool
     */
    protected $listenMessageReceived = false;


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
        $this->channel = $this->connection->channel();
        $this->channel->confirm_select();
        /** @var \TYPO3\CMS\Core\Log\LogManager $logManager */
        $logManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class);
        /** @var \Psr\Log\LoggerInterface $logger */
        $this->logger = $logManager->getLogger(__CLASS__);
    }


    public function disconnect()
    {
        $this->channel->close();
        $this->connection->close();
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
        $this->channel->basic_publish($msg, $exchange, $routing);
        $this->channel->wait_for_pending_acks();
    }

    /**
     * @param string $queue
     * @param bool $lock
     * @return null|\TYPO3Incubator\Jobqueue\Message
     */
    public function get($queue, $lock = true)
    {
        $this->declareQueue($queue);
        $message = $this->channel->basic_get($queue, false);
        if ($message instanceof \PhpAmqpLib\Message\AMQPMessage) {
            $msg = $this->buildMessage($message);
            if ($msg instanceof \TYPO3Incubator\Jobqueue\Message) {
                $msg->setMeta('amqp.queue', $queue);
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
            $this->listenMessageReceived = true;
            $msg->setMeta('amqp.delivery_tag', $message->delivery_info['delivery_tag']);
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
            $this->declareQueue($queue);
            $this->logger->debug('basic_consume ' . $this->getConsumerTag($queue));
            $this->channel->basic_consume($queue, $this->getConsumerTag($queue), false, false, false, false,
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
        $this->channel->basic_cancel($this->getConsumerTag($queue), false, true);
    }


    /**
     * @param bool $blocking
     * @param null $callable
     */
    public function wait($blocking = false, $callable = null)
    {
        $blocking = !$blocking;
        if ($callable === null) {
            $this->channel->wait(null, $blocking);
        } else {
            /*
            we could make a blocking call that waits until the broker sends us
            sth new. but that would cause this listener not to respond to signals
            anymore. therefor we make a non blocking wait call, dispatch pending signals
            and sleep a little. however we will not get notice if the wait resulted in a new
            message being delivered. for that reason we use a boolean flag which will be set
            to true inside our callback that is fired once a message was received.
            */
            $this->listenMessageReceived = false;
            while(!$this->listenMessageReceived) {
                $this->channel->wait(null, true);
                call_user_func($callable);
                usleep(1000);
            }
        }
    }

    /**
     * @param int $limit
     * @return void
     */
    public function setMessageLimit($limit)
    {
        $this->channel->basic_qos(0, $limit, true);
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
        $this->channel->basic_publish($msg, $exchange, $routing);
        $this->channel->wait_for_pending_acks_returns();
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
            $this->logger->debug('basic_ack', ['delivery_tag' => $deliveryTag]);
            if (($channel = $message->getMeta('amqp.channel', null)) === null) {
                $channel = $this->channel;
            }
            $channel->basic_ack($deliveryTag);
            $channel->wait_for_pending_acks();
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
        $deliveryTag = $message->getMeta('amqp.delivery_tag', null);
        if ($deliveryTag !== null) {
            $this->logger->debug('basic_ack', ['delivery_tag' => $deliveryTag]);
            if (($channel = $message->getMeta('amqp.channel', null)) === null) {
                $channel = $this->channel;
            }
            $channel->basic_ack($deliveryTag);
            $channel->wait_for_pending_acks();
        }
        $this->add($message->getMeta('amqp.queue'), $message);
    }


    /**
     * @param string $queue
     * @return int
     */
    public function count($queue)
    {
        if (!$this->queueOverrideExists($queue)) {
            $result = $this->channel->queue_declare($queue, true, true, false, false, false);
            if (is_array($result)) {
                return (int)$result[1];
            }
        }
        return 0;
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
        if (!isset($this->declaredExchanges[$exchange])) {
            $this->channel->exchange_declare($exchange, $type, $passive, $durable, $auto_delete, $internal, true,
                $arguments, null);
            $this->declaredExchanges[$exchange] = true;
        }
    }

    /**
     * @param $queue
     * @return array 0 -> exchange, 1 -> routing key
     */
    protected function getPublishInformation($queue)
    {
        $exchange = '';
        $routing = '';
        if (!$this->queueOverrideExists($queue)) {
            $exchange = $this->defaultExchange;
            $routing = $this->getDefaultRoutingKey($queue);
            $this->declareExchange($this->defaultExchange, 'direct', false, true, false);
            // make sure the queue exists
            $this->declareQueue($queue);
            $this->bindQueue($queue, $this->defaultExchange, $routing);
        } else {
            // @todo initialize according to override config
        }
        return array($exchange, $routing);
    }

    /**
     * @param string $queue
     */
    protected function declareQueue($queue)
    {
        if (!isset($this->declaredQueues[$queue])) {
            if (!$this->queueOverrideExists($queue)) {
                $this->channel->queue_declare($queue, false, true, false, false, true);
                $this->declaredQueues[$queue] = true;
            }
        }
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
            $this->channel->queue_bind($queue, $exchange, $key);
            $this->boundQueues[$hash] = true;
        }
    }

    /**
     * @param string $queue
     * @return bool
     */
    protected function queueOverrideExists($queue)
    {
        return isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['queue']['configuration'][$this->identifier]['queues'][$queue]);
    }

    /**
     * @param $queue
     * @return string
     */
    protected function getDefaultRoutingKey($queue)
    {
        return $queue . '-direct';
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
        while (!is_array($payload)) {
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
