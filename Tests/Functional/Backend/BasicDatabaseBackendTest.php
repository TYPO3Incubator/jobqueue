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
class BasicDatabaseBackendTest extends AbstractBasicBackendTest
{

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



}
