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
     * Sets up this test case.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->subject = $this->getAccessibleMock(\TYPO3Incubator\Jobqueue\Backend\AmqpBackend::class, array('_dummy'), array($GLOBALS['TYPO3_CONF_VARS']['SYS']['queue']['configuration']['rabbitmq']['options']));
    }

    /**
     * Tears down this test case.
     */
    protected function tearDown()
    {
        parent::tearDown();

        unset($this->subject);
    }
}