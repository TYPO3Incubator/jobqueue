<?php
namespace TYPO3Incubator\Jobqueue\Tests\Unit;

use org\bovigo\vfs\vfsStream;
use TYPO3\CMS\Core\Tests\UnitTestCase;

class UtilityTest extends UnitTestCase
{

    /**
     * @param string $handler
     * @param $expectedResult
     *
     * @test
     * @dataProvider handlerDefinitionIsStaticMethodDataProvider
     */
    public function handlerDefinitionIsStaticMethod($handler, $expectedResult)
    {
        $result = \TYPO3Incubator\Jobqueue\Utility::isStaticMethodHandler($handler);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @return array
     */
    public function handlerDefinitionIsStaticMethodDataProvider()
    {
        return [
            'FQC with static method' => [
                'TYPO3Incubator\Handler\ExampleJobHandler::sleep',
                true
            ],
            'FQC with object method' => [
                'TYPO3Incubator\Handler\ExampleJobHandler->sleep',
                false
            ],
            'class with static method' => [
                'ExampleJobHandler::sleep',
                true
            ],
            'class with object method' => [
                'ExampleJobHandler->sleep',
                false
            ],
            'oldschool class name with static method' => [
                'Tx_Jobqueue_Handler_ExampleJobHandler::sleep',
                true
            ],
            'oldschool class name with object method' => [
                'Tx_Jobqueue_Handler_ExampleJobHandler->sleep',
                false
            ],
            'even older userobj reference with file and object method' => [
                'EXT:jobqueue/Classes/Handler/ExampleJobHandler.php:TYPO3Incubator\Handler\ExampleJobHandler->sleep',
                false
            ],
            'even older userobj reference with file and static method' => [
                'EXT:jobqueue/Classes/Handler/ExampleJobHandler.php:TYPO3Incubator\Handler\ExampleJobHandler::sleep',
                false
            ]
        ];
    }


    /**
     * @param string $handler
     * @param bool $expectedResult
     *
     * @test
     * @dataProvider handlerDefinitionsValidateDataProvider
     */
    public function handlerDefinitionsValidate($handler, $expectedResult)
    {
        $result = \TYPO3Incubator\Jobqueue\Utility::validHandler($handler);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @return array
     */
    public function handlerDefinitionsValidateDataProvider()
    {
        return [
            'FQC with static method' => [
                'TYPO3Incubator\Handler\ExampleJobHandler::sleep',
                true
            ],
            'FQC with object method' => [
                'TYPO3Incubator\Handler\ExampleJobHandler->sleep',
                true
            ],
            'class with static method' => [
                'ExampleJobHandler::sleep',
                true
            ],
            'class with object method' => [
                'ExampleJobHandler->sleep',
                true
            ],
            'oldschool class name with static method' => [
                'Tx_Jobqueue_Handler_ExampleJobHandler::sleep',
                true
            ],
            'oldschool class name with object method' => [
                'Tx_Jobqueue_Handler_ExampleJobHandler->sleep',
                true
            ],
            'even older userobj reference with file and object method' => [
                'EXT:jobqueue/Classes/Handler/ExampleJobHandler.php:TYPO3Incubator\Handler\ExampleJobHandler->sleep',
                false
            ],
            'even older userobj reference with file and static method' => [
                'EXT:jobqueue/Classes/Handler/ExampleJobHandler.php:TYPO3Incubator\Handler\ExampleJobHandler::sleep',
                false
            ]
        ];
    }

    /**
     * @param string $handler
     * @param bool $exception
     * @param string $class
     * @param string $method
     *
     * @test
     * @dataProvider extractsHandlerClassAndMethodDataProvider
     */
    public function extractsHandlerClassAndMethod($handler, $exception, $class, $method)
    {
        if($exception === true) {
            $this->setExpectedException('\InvalidArgumentException');
        }
        $result = \TYPO3Incubator\Jobqueue\Utility::extractHandlerClassAndMethod($handler);
        $this->assertEquals($class, $result['class']);
        $this->assertEquals($method, $result['method']);
    }

    /**
     * @return array
     */
    public function extractsHandlerClassAndMethodDataProvider()
    {
        return [
            'FQC with static method' => [
                'TYPO3Incubator\Handler\ExampleJobHandler::sleep',
                false,
                'TYPO3Incubator\Handler\ExampleJobHandler',
                'sleep'
            ],
            'FQC with object method' => [
                'TYPO3Incubator\Handler\ExampleJobHandler->sleep',
                false,
                'TYPO3Incubator\Handler\ExampleJobHandler',
                'sleep'
            ],
            'class with static method' => [
                'ExampleJobHandler::sleep',
                false,
                'ExampleJobHandler',
                'sleep'
            ],
            'class with object method' => [
                'ExampleJobHandler->sleep',
                false,
                'ExampleJobHandler',
                'sleep'
            ],
            'oldschool class name with static method' => [
                'Tx_Jobqueue_Handler_ExampleJobHandler::sleep',
                false,
                'Tx_Jobqueue_Handler_ExampleJobHandler',
                'sleep'
            ],
            'oldschool class name with object method' => [
                'Tx_Jobqueue_Handler_ExampleJobHandler->sleep',
                false,
                'Tx_Jobqueue_Handler_ExampleJobHandler',
                'sleep'
            ],
            'even older userobj reference with file and object method' => [
                'EXT:jobqueue/Classes/Handler/ExampleJobHandler.php:TYPO3Incubator\Handler\ExampleJobHandler->sleep',
                true,
                'dummy',
                'dummy'
            ],
            'even older userobj reference with file and static method' => [
                'EXT:jobqueue/Classes/Handler/ExampleJobHandler.php:TYPO3Incubator\Handler\ExampleJobHandler::sleep',
                true,
                'dummy',
                'dummy'
            ]
        ];
    }

    /**
     * @test
     * @dataProvider throwsExceptionWhenParsingFromInvalidDataDataProvider
     * @expectedException \InvalidArgumentException
     */
    public function throwsExceptionWhenParsingFromInvalidData($encoded)
    {
        \TYPO3Incubator\Jobqueue\Utility::parseMessage($encoded);
    }

    /**
     * @return array
     */
    public function throwsExceptionWhenParsingFromInvalidDataDataProvider()
    {
        return [
            'invalid json' => [
                '"{\\"handler\\":\\"TYPO3Incubator\\\\\\\\Jobqueue\\\\\\\\Handler\\\\\\\\ExampleJobHandler->sleep\\,\\data\\":{\\"duration\\":5},\\"attempts\\":0,\\"nextexecution\\":1469561788}"'
            ],
            'missing required key' => [
                '"{\\"handler\\":\\"TYPO3Incubator\\\\\\\\Jobqueue\\\\\\\\Handler\\\\\\\\ExampleJobHandler->sleep\\",\\"data\\":{\\"duration\\":5},\\"attempts\\":0}"'
            ],
            'missing required key' => [
                '"{\\"handler\\":\\"TYPO3Incubator\\\\\\\\Jobqueue\\\\\\\\Handler\\\\\\\\ExampleJobHandler->sleep\\",\\"attempts\\":0,\\"nextexecution\\":1469561788}"'
            ],
            'missing required key' => [
                '"{\\"handler\\":\\"TYPO3Incubator\\\\\\\\Jobqueue\\\\\\\\Handler\\\\\\\\ExampleJobHandler->sleep\\",\\"data\\":{\\"duration\\":5},\\"nextexecution\\":1469561788}"'
            ],
            'missing required key' => [
                '"{\\"data\\":{\\"duration\\":5},\\"attempts\\":0,\\"nextexecution\\":1469561788}"'
            ]
        ];
    }

    /**
     * @test
     * @dataProvider parsesMessagesDataProvider
     * @param string $encoded
     * @param string $handler
     * @param string $data
     * @param int $attempts
     * @param int $nextexcution
     */
    public function parsesMessagesFromStream($encoded, $handler, $data,  $attempts, $nextexcution)
    {
        $root = vfsStream::setup('root', null, [
            'payload.json' => $encoded
        ]);
        $handle = fopen($root->url() . '/payload.json', 'r');
        $reflection = new \ReflectionProperty(\TYPO3Incubator\Jobqueue\Utility::class, 'stdIn');
        $reflection->setAccessible(true);
        $reflection->setValue(\TYPO3Incubator\Jobqueue\Utility::class, $handle);
        $message = \TYPO3Incubator\Jobqueue\Utility::parseMessage();
        $this->assertEquals($handler, $message->getHandler());
        $this->assertEquals($data, $message->getData());
        $this->assertEquals($attempts, $message->getAttempts());
        $this->assertEquals($nextexcution, $message->getNextExecution());
    }

    /**
     * @test
     * @dataProvider parsesMessagesDataProvider
     * @param string $encoded
     * @param string $handler
     * @param array $data
     * @param int $attempts
     * @param int $nextexcution
     */
    public function parsesMessagesFromString($encoded, $handler, $data,  $attempts, $nextexcution)
    {
        $message = \TYPO3Incubator\Jobqueue\Utility::parseMessage($encoded);
        $this->assertEquals($handler, $message->getHandler());
        $this->assertEquals($data, $message->getData());
        $this->assertEquals($attempts, $message->getAttempts());
        $this->assertEquals($nextexcution, $message->getNextExecution());
    }

    /**
     * @return array
     */
    public function parsesMessagesDataProvider()
    {
        return [
            [
                '"{\\"handler\\":\\"TYPO3Incubator\\\\\\\\Jobqueue\\\\\\\\Handler\\\\\\\\ExampleJobHandler->sleep\\",\\"data\\":{\\"duration\\":5},\\"attempts\\":0,\\"nextexecution\\":1469561788}"',
                'TYPO3Incubator\\Jobqueue\\Handler\\ExampleJobHandler->sleep',
                [
                    'duration' => 5
                ],
                0,
                1469561788
            ],[
                '"{\\"handler\\":\\"TYPO3Incubator\\\\\\\\Jobqueue\\\\\\\\Handler\\\\\\\\ExampleJobHandler::sleep\\",\\"data\\":{\\"duration\\":9},\\"attempts\\":2,\\"nextexecution\\":1469891788}"',
                'TYPO3Incubator\\Jobqueue\\Handler\\ExampleJobHandler::sleep',
                [
                    'duration' => 9
                ],
                2,
                1469891788
            ]
        ];
    }

}
