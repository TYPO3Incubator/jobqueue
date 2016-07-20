<?php

namespace TYPO3Incubator\Jobqueue\Tests\Functional\Backend;


abstract class AbstractBasicBackendTest extends \TYPO3Incubator\Jobqueue\Tests\Functional\AbstractTestCase
{

    /**
     * @var \TYPO3Incubator\Jobqueue\Backend\BackendInterface
     */
    protected $subject;

    /**
     * @var string
     */
    protected $handler = \TYPO3Incubator\Jobqueue\Handler\ExampleJobHandler::class.'->sleep';

    /**
     * @var array
     */
    protected $data = [
        'duration' => 5
    ];

    /**
     * @return \TYPO3Incubator\Jobqueue\Message
     */
    protected function createMessage()
    {
        return new \TYPO3Incubator\Jobqueue\Message($this->handler, $this->data);
    }

    /**
     * @param string $queue
     * @param int $count
     *
     * @test
     * @dataProvider testQueueCountDataProvider
     */
    public function testQueueCount($queue, $count)
    {
        // make sure we have a clean queue
        $this->cleanQueue($queue);
        for($i = 0; $i < $count; $i++) {
            $this->subject->add($queue,$this->createMessage());
        }
        $this->assertEquals($this->subject->count($queue), $count, 'message count on queue matches');
        // cleanup queue for next test
        $this->cleanQueue($queue);
    }

    /**
     * @param string $queue
     */
    protected function cleanQueue($queue)
    {
        while(($msg = $this->subject->get($queue)) instanceof \TYPO3Incubator\Jobqueue\Message) {
            $this->subject->remove($msg);
        }
    }

    /**
     * @return array
     */
    public function testQueueCountDataProvider()
    {
        return [
            ['default', 20],
            ['internal', 15],
            ['default', 37],
            ['test', 127],
            ['default', 243],
            ['internal', 392]
        ];
    }

    /**
     *
     * @test
     */
    public function testMessageLifeCycle()
    {
        // queue should be empty
        $this->assertEquals($this->subject->get('default'), null, 'returns null since queue is empty');

        $nextExec = time();
        $newMessage = $this->createMessage();
        $newMessage->setNextExecution($nextExec);
        $this->subject->add('default', $newMessage);
        // queue should have 1 message
        $message = $this->subject->get('default');

        // single message should be locked -> backend does not return a message
        $this->assertEquals($this->subject->get('default'), null, 'returns null since queue is empty');

        // values match inserted
        $this->assertEquals($message->getHandler(), $this->handler, 'handler matches inserted value');
        $this->assertEquals($message->getData(), $this->data, 'data matches inserted value');
        $this->assertEquals($message->getAttempts(), 0, 'attempts matches inserted value');
        $this->assertEquals($message->getNextExecution(), $nextExec, 'nextexecution matches inserted value');

        // message should be removed from queue
        $this->subject->remove($message);

        unset($message);

        // queue should be empty again and backend should return null
        $this->assertEquals($this->subject->get('default'), null, 'returns null since queue is empty');

        // add a new message
        $this->subject->add('default', $newMessage);
        // retrieve that message
        $message = $this->subject->get('default');
        // update message values
        $newExecTime = time();
        $message->setAttempts($message->getAttempts()+1);
        $message->setNextExecution($nextExec);
        // update the message specified
        $this->subject->update($message);
        unset($message);
        // retrieve the single message which now should be unlocked again
        $message = $this->subject->get('default');

        $this->assertEquals($message->getHandler(), $this->handler, 'handler was not changed');
        $this->assertEquals($message->getData(), $this->data, 'data was not changed');
        $this->assertEquals($message->getAttempts(), 1, 'attempts were updated');
        $this->assertEquals($message->getNextExecution(), $newExecTime, 'nextexecution was updated');

        // cleanup, remove record from queue
        $this->subject->remove($message);

    }

}