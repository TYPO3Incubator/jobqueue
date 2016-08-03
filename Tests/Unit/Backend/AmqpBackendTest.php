<?php
namespace TYPO3Incubator\Jobqueue\Tests\Unit\Backend;

use TYPO3\CMS\Core\Tests\UnitTestCase;

class AmqpBackendTest extends UnitTestCase
{

    /**
     * @var string
     */
    protected $encoded = '"{\\"handler\\":\\"TYPO3Incubator\\\\\\\\Jobqueue\\\\\\\\Handler\\\\\\\\ExampleJobHandler->sleep\\",\\"data\\":{\\"duration\\":5},\\"attempts\\":0,\\"nextexecution\\":1469561788}"';

    /**
     * @var \TYPO3Incubator\Jobqueue\Backend\AmqpBackend
     */
    protected $subject;

    protected function setUp()
    {
        parent::setUp();
        $this->subject = $this->getAccessibleMock(\TYPO3Incubator\Jobqueue\Backend\AmqpBackend::class, array(), array(), '', false);
    }

    /**
     * @test
     */
    public function buildsMessageFromAmqpMessage()
    {
        $deliveryTag = 'testtag';
        $channel = 'testChannelNotAnObject';
        $amqpMessage = new \PhpAmqpLib\Message\AMQPMessage($this->encoded);
        $amqpMessage->delivery_info['delivery_tag'] = $deliveryTag;
        $amqpMessage->delivery_info['channel'] = $channel;
        /** @var \TYPO3Incubator\Jobqueue\Message $message */
        $message = $this->callInaccessibleMethod($this->subject, 'buildMessage', $amqpMessage);
        $this->assertTrue($message instanceof \TYPO3Incubator\Jobqueue\Message);
        $this->assertEquals(0, $message->getAttempts());
        $this->assertEquals(1469561788, $message->getNextExecution());
        $this->assertEquals(['duration' => 5], $message->getData());
        $this->assertEquals('TYPO3Incubator\\Jobqueue\\Handler\\ExampleJobHandler->sleep', $message->getHandler());
        $this->assertEquals($deliveryTag, $message->getMeta('amqp.delivery_tag'));
        $this->assertEquals($channel, $message->getMeta('amqp.channel'));
    }

    /**
     * @test
     */
    public function buildsAmqpMessageFromMessage()
    {
        $message = \TYPO3Incubator\Jobqueue\Utility::parseMessage($this->encoded);
        /** @var \PhpAmqpLib\Message\AMQPMessage $amqpMessage */
        $amqpMessage = $this->callInaccessibleMethod($this->subject, 'buildAMQPMessage', $message);
        $this->assertTrue($amqpMessage instanceof \PhpAmqpLib\Message\AMQPMessage);
        $this->assertEquals($this->encoded, $amqpMessage->body);
        $this->assertEquals(\PhpAmqpLib\Message\AMQPMessage::DELIVERY_MODE_PERSISTENT, $amqpMessage->get('delivery_mode'));
        $this->assertEquals('application/json', $amqpMessage->get('content_type'));
    }

    protected function tearDown()
    {
        parent::tearDown();
        unset($this->subject);
    }

}