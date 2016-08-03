<?php
namespace TYPO3Incubator\Jobqueue\Tests\Unit\Backend;

use TYPO3\CMS\Core\Tests\UnitTestCase;

class DatabaseBackendTest extends UnitTestCase
{

    /**
     * @var \TYPO3Incubator\Jobqueue\Backend\AmqpBackend
     */
    protected $subject;

    protected function setUp()
    {
        parent::setUp();
        $this->subject = $this->getAccessibleMock(\TYPO3Incubator\Jobqueue\Backend\DatabaseBackend::class, array(), array(), '', false);
    }

    /**
     * @test
     */
    public function throwsExceptionIfNoUidIsAvailable()
    {
        $this->setExpectedException(\InvalidArgumentException::class);
        $message = new \TYPO3Incubator\Jobqueue\Message('handler', []);
        $this->callInaccessibleMethod($this->subject, 'getUidFromMessage', $message);
    }

    /**
     * @test
     */
    public function extractsUidFromMessage()
    {
        $testValue = 4;
        $message = new \TYPO3Incubator\Jobqueue\Message('handler', []);
        $message->setMeta('db.uid', $testValue);
        $returnedValue = $this->callInaccessibleMethod($this->subject, 'getUidFromMessage', $message);
        $this->assertEquals($testValue, $returnedValue);
    }

    /**
     * @test
     * @dataProvider transformationTestsDataProvider
     */
    public function buildsMessageFromRow($data)
    {
        /** @var \TYPO3Incubator\Jobqueue\Message $message */
        $message = $this->callInaccessibleMethod($this->subject, 'getMessageFromRow', $data);
        $this->assertEquals($data['uid'], $message->getMeta('db.uid'));
        $this->assertEquals($data['queue'], $message->getMeta('db.queue'));
        $this->assertEquals($data['nextexecution'], $message->getNextExecution());
        $this->assertEquals($data['attempts'], $message->getAttempts());
        $this->assertEquals($data['handler'], $message->getHandler());
        $this->assertEquals($data['data'], $message->getData());
    }

    /**
     * @test
     * @dataProvider transformationTestsDataProvider
     */
    public function extractsValuesFromMessageForDatabase($data)
    {
        $message = new \TYPO3Incubator\Jobqueue\Message($data['handler'], $data['data']);
        $message->setAttempts($data['attempts'])
            ->setNextExecution($data['nextexecution']);

        $rowValues = $this->callInaccessibleMethod($this->subject, 'getValuesFromMessage', $message);

        $this->assertEquals($data['attempts'], $rowValues['attempts']);
        $this->assertEquals($data['nextexecution'], $rowValues['nextexecution']);
        $this->assertEquals($data['payload'], $rowValues['payload']);
    }

    /**
     * @return array
     */
    public function transformationTestsDataProvider()
    {
        return [
            [
                [
                    'uid' => 7,
                    'payload_unescaped' => '{"handler":"TYPO3Incubator\\Jobqueue\\Handler\\ExampleJobHandler->sleep","data":{"duration":5}}',
                    'payload' => '{"handler":"TYPO3Incubator\\\\Jobqueue\\\\Handler\\\\ExampleJobHandler->sleep","data":{"duration":5}}',
                    'queue' => 'default',
                    'nextexecution' => 1470216267,
                    'attempts' => 3,
                    'handler' => 'TYPO3Incubator\\Jobqueue\\Handler\\ExampleJobHandler->sleep',
                    'data' => ['duration' => 5]
                ]
            ],
            [
                [
                    'uid' => 3,
                    'payload_unescaped' => '{"handler":"TYPO3Incubator\\Jobqueue\\Handler\\ExampleJobHandler::sleep","data":{"duration":2}}',
                    'payload' => '{"handler":"TYPO3Incubator\\\\Jobqueue\\\\Handler\\\\ExampleJobHandler::sleep","data":{"duration":2}}',
                    'queue' => 'mail',
                    'nextexecution' => 1470366267,
                    'attempts' => 1,
                    'handler' => 'TYPO3Incubator\\Jobqueue\\Handler\\ExampleJobHandler::sleep',
                    'data' => ['duration' => 2]
                ]
            ],
            [
                [
                    'uid' => 5,
                    'payload_unescaped' => '{"handler":"TYPO3Incubator\\Jobqueue\\Handler\\ExampleJobHandler::sleep","data":{"duration":1}}',
                    'payload' => '{"handler":"TYPO3Incubator\\\\Jobqueue\\\\Handler\\\\ExampleJobHandler::sleep","data":{"duration":1}}',
                    'queue' => 'mail',
                    'nextexecution' => 1470123267,
                    'attempts' => 2,
                    'handler' => 'TYPO3Incubator\\Jobqueue\\Handler\\ExampleJobHandler::sleep',
                    'data' => ['duration' => 1]
                ]
            ]
        ];
    }

    protected function tearDown()
    {
        parent::tearDown();
        unset($this->subject);
    }

}