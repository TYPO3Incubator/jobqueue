<?php
/**
 * Created by PhpStorm.
 * User: dustin
 * Date: 20.07.16
 * Time: 20:27
 */

namespace Functional\Backend;


class BasicAmqpBackendTest extends \TYPO3Incubator\Jobqueue\Tests\Functional\Backend\AbstractBasicBackendTest
{

    /**
     * @var \PhpAmqpLib\Connection\AMQPStreamConnection
     */
    protected $connection;

    /**
     * Sets up this test case.
     */
    protected function setUp()
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['queue']['configuration']['rabbitmq']['virtualQueues'] = [
            'fanoutqueue01' => [
                'exchange' => 'fanout.test',
                'routing' => 'key-is-ignored-in-fanout-exchange'
            ],
            'fanoutqueue02' => [
                'exchange' => 'fanout.test',
                'routing' => 'different-key-is-also-ignored-in-fanout-exchange'
            ],
            'topicqueue01' => [
                'exchange' => 'topic.test',
                'routing' => 'topictest.topic1'
            ],
            'topicqueue02' => [
                'exchange' => 'topic.test',
                'routing' => 'differenttopic.topic1'
            ],
            'topicqueue03' => [
                'exchange' => 'topic.test',
                'routing' => 'topictest.topic2'
            ],
        ];
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['queue']['configuration']['rabbitmq']['queues']['fanout2'] = [
            'auto_delete' => true
        ];
        $config = array_merge($GLOBALS['TYPO3_CONF_VARS']['SYS']['queue']['configuration']['rabbitmq']['options'], ['identifier' => 'rabbitmq']);
        $this->subject = $this->getAccessibleMock(\TYPO3Incubator\Jobqueue\Backend\AmqpBackend::class, array('_dummy'), array($config));
        $connectionReflection = new \ReflectionProperty(\TYPO3Incubator\Jobqueue\Backend\AmqpBackend::class, 'connection');
        $connectionReflection->setAccessible(true);
        /** @var \PhpAmqpLib\Connection\AMQPStreamConnection $connection */
        $this->connection = $connectionReflection->getValue($this->subject);
    }

    /**
     * @param string $queue
     */
    protected function cleanQueue($queue)
    {
        try {
            $channel = $this->connection->channel();
            $channel->queue_purge($queue);
            $channel->close();
        } catch (\PhpAmqpLib\Exception\AMQPProtocolChannelException $e) {
            // ...
        }
    }

    /**
     * Tears down this test case.
     */
    protected function tearDown()
    {
        parent::tearDown();

        unset($this->subject);
    }

    /**
     * @test
     * @dataProvider testQueueOvverideWithFanoutDataProvider
     */
    public function testQueueOverrideWithFanout($virtualQueue, $queueCount)
    {
        // clean queues
        foreach($queueCount as $queue => $count) {
            $this->cleanQueue($queue);
        }


        $message = $this->createMessage();
        $message->setNextExecution(time());

        $this->subject->add($virtualQueue, $message);

        // check message count
        foreach($queueCount as $queue => $count) {
            $this->assertEquals($count, $this->subject->count($queue), "message count on queue '{$queue}' should be {$count}");
        }

        // clean queues
        foreach($queueCount as $queue => $count) {
            $this->cleanQueue($queue);
        }
    }

    /**
     * @return array
     */
    public function testQueueOvverideWithFanoutDataProvider()
    {
        return [
            [
                'fanoutqueue01', [
                    'fanout1' => 1,
                    'fanout2' => 1
                ]
            ],
            [
                'fanoutqueue02', [
                    'fanout1' => 1,
                    'fanout2' => 1
                ]
            ]
        ];
    }


    /**
     * @test
     * @dataProvider testQueueOvverideWithTopicsDataProvider
     */
    public function testQueueOverrideWithTopics($virtualQueue, $queueCount)
    {
        // clean queues
        foreach($queueCount as $queue => $count) {
            $this->cleanQueue($queue);
        }


        $message = $this->createMessage();
        $message->setNextExecution(time());

        $this->subject->add($virtualQueue, $message);

        // check message count
        foreach($queueCount as $queue => $count) {
            $this->assertEquals($count, $this->subject->count($queue), "message count on queue '{$queue}' should be {$count}");
        }

        // clean queues
        foreach($queueCount as $queue => $count) {
            $this->cleanQueue($queue);
        }
    }

    /**
     * @return array
     */
    public function testQueueOvverideWithTopicsDataProvider()
    {
        return [
            [
                'topicqueue01', [
                    'topic1' => 1,
                    'topic2' => 1
                ]
            ],
            [
                'topicqueue02', [
                    'topic1' => 1,
                    'topic2' => 0
                ]
            ],
            [
                'topicqueue03', [
                    'topic1' => 0,
                    'topic2' => 1
                ]
            ]
        ];
    }


}