<?php
namespace TYPO3Incubator\Jobqueue\Tests\Functional\Backend;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
use TYPO3Incubator\Jobqueue\Tests\Functional\AbstractTestCase;


/**
 * Class DatabaseBackendTest
 */
class DatabaseBackendTest extends AbstractTestCase
{
    /**
     * @var \TYPO3Incubator\Jobqueue\Backend\DatabaseBackend
     */
    protected $subject;

    /**
     * Sets up this test case.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->subject = $this->getAccessibleMock(\TYPO3Incubator\Jobqueue\Backend\DatabaseBackend::class, array('_dummy'), array());
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
     *
     * @test
     */
    public function testMessageLifeCycle()
    {
        // queue should be empty
        $this->assertEquals($this->subject->get('default'), null);

        $handler = \TYPO3Incubator\Jobqueue\Handler\ExampleJobHandler::class.'->sleep';
        $data = [
            'duration' => 5
        ];
        $nextExec = time();
        $newMessage = new \TYPO3Incubator\Jobqueue\Message($handler, $data);
        $newMessage->setNextExecution($nextExec);
        $this->subject->add('default', $newMessage);
        // queue should have 1 message
        $message = $this->subject->get('default');

        // single message should be locked -> backend does not return a message
        $this->assertEquals($this->subject->get('default'), null);

        // values match inserted
        $this->assertEquals($message->getHandler(), $handler);
        $this->assertEquals($message->getData(), $data);
        $this->assertEquals($message->getAttempts(), 0);
        $this->assertEquals($message->getNextExecution(), $nextExec);

        // message should be removed from queue
        $this->subject->remove($message);

        unset($message);

        // queue should be empty again
        $this->assertEquals($this->subject->get('default'), null);

        // add a new message
        $this->subject->add('default', $newMessage);
        // retrieve single message
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

        $this->assertEquals($message->getHandler(), $handler);
        $this->assertEquals($message->getData(), $data);
        $this->assertEquals($message->getAttempts(), 1);
        $this->assertEquals($message->getNextExecution(), $newExecTime);

    }




}
