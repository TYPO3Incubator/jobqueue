<?php
namespace TYPO3Incubator\Jobqueue\Tests\Unit;

use TYPO3\CMS\Core\Tests\UnitTestCase;

class AmqpUtilityTest extends UnitTestCase
{

    /**
     * @var \TYPO3Incubator\Jobqueue\AmqpUtility
     */
    protected $subject;

    protected function setUp()
    {
        parent::setUp();
        $this->subject = $this->getPreparedMock('rabbitmq');
        $this->subject->initializeObject();
    }

    /**
     * @param $identifier
     * @return \PHPUnit_Framework_MockObject_MockObject|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface
     */
    protected function getPreparedMock($identifier)
    {
        $mock = $this->getAccessibleMock(\TYPO3Incubator\Jobqueue\AmqpUtility::class, null, [$identifier]);
        $this->inject($mock, 'configuration', $this->getConfigurationMock());
        return $mock;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface
     */
    protected function getConfigurationMock()
    {
        $configuration = $this->getAccessibleMock(\TYPO3Incubator\Jobqueue\Configuration::class, ['getBackendConfiguration']);
        $configuration->method('getBackendConfiguration')
            ->will($this->returnValueMap([
                ['default', [
                    'backend' => \TYPO3Incubator\Jobqueue\Backend\DatabaseBackend::class
                ]],
                ['rabbitmq', [
                    'backend' => \TYPO3Incubator\Jobqueue\Backend\AmqpBackend::class,
                    'queues' => [
                        'testqueue01' => [
                            'passive' => true
                        ],
                        'testqueue02' => [
                            'durable' => false
                        ],
                        'testqueue03' => [
                            'exclusive' => true
                        ],
                        'testqueue04' => [
                            'auto_delete' => false
                        ],
                        'testqueue05' => [
                            'arguments' => null
                        ],
                        'testqueue06' => [
                            'passive' => true,
                            'exclusive' => true
                        ]
                    ],
                    'virtualQueues' => [
                        'vqueue01' => [
                            'exchange' => 'exchange01',
                            'routing' => 'routing01'
                        ]
                    ]
                ]],
                ['nonexistant', null]
            ]));
        return $configuration;
    }

    /**
     * @test
     */
    public function throwsExceptionWhenInitializedOnNonAmqpBackend()
    {
        $this->setExpectedException(\InvalidArgumentException::class);
        $subject = $this->getPreparedMock('default');
        $subject->initializeObject();
    }

    /**
     * @test
     */
    public function throwsExceptionWhenInitializedOnUnconfiguredBackend()
    {
        $this->setExpectedException(\InvalidArgumentException::class);
        $subject = $this->getPreparedMock('nonexistant');
        $subject->initializeObject();
    }

    /**
     * @test
     * @dataProvider isVirtualQueueReturnsCorrectValueDataProvider
     * @param string $queue
     * @param bool $expectedResult
     */
    public function isVirtualQueueReturnsCorrectValue($queue, $expectedResult)
    {
        $this->assertEquals($expectedResult, $this->subject->isVirtualQueue($queue));
    }

    /**
     * @return array
     */
    public function isVirtualQueueReturnsCorrectValueDataProvider()
    {
        return [
            [
                'default',
                false
            ],[
                'failed',
                false
            ],[
                'vqueue01',
                true
            ],[
                'mail',
                false
            ],[
                'api1',
                false
            ]
        ];
    }

    /**
     * @test
     */
    public function defaultExchangeDefinitionIsCorrect()
    {
        $this->assertEquals([
            'exchange' => 'typo3.direct',
            'type' => 'direct',
            'passive' => false,
            'durable' => true,
            'auto_delete' => false,
            'internal' => false,
            'arguments' => null
        ], $this->subject->getDefaultExchangeDefinition());
    }

    /**
     * @test
     * @dataProvider getPublishInformationReturnsCorrectValuesDataProvider
     */
    public function getPublishInformationReturnsCorrectValues($queue, $definition)
    {
        $this->assertEquals($definition, $this->subject->getPublishInformation($queue));
    }

    /**
     * @return array
     */
    public function getPublishInformationReturnsCorrectValuesDataProvider()
    {
        return [
            ['default', [
                'typo3.direct',
                'default-direct'
            ]],
            ['failed', [
                'typo3.direct',
                'failed-direct'
            ]],
            ['vqueue01', [
                'exchange01',
                'routing01'
            ]]
        ];
    }

    /**
     * @param string $queue
     * @param array $definition
     * @test
     * @dataProvider getQueueDefinitionReturnsCorrectValuesDataProvider
     */
    public function getQueueDefinitionReturnsCorrectValues($queue, $definition)
    {
        $this->assertEquals($definition, $this->subject->getQueueDefinition($queue));
    }

    /**
     * @return array
     */
    public function getQueueDefinitionReturnsCorrectValuesDataProvider()
    {
        return [
            [
                'default',
                [
                    'queue' => 'default',
                    'passive' => false,
                    'durable' => true,
                    'exclusive' => false,
                    'auto_delete' => true,
                    'arguments' => [
                        'x-dead-letter-exchange' => ['S', 'typo3.direct'],
                        'x-dead-letter-routing-key' => ['S', 'failed-direct']
                    ]
                ]
            ],
            [
                'mail',
                [
                    'queue' => 'mail',
                    'passive' => false,
                    'durable' => true,
                    'exclusive' => false,
                    'auto_delete' => true,
                    'arguments' => [
                        'x-dead-letter-exchange' => ['S', 'typo3.direct'],
                        'x-dead-letter-routing-key' => ['S', 'failed-direct']
                    ]
                ]
            ],
            [
                'testqueue01',
                [
                    'queue' => 'testqueue01',
                    'passive' => true,
                    'durable' => true,
                    'exclusive' => false,
                    'auto_delete' => true,
                    'arguments' => [
                        'x-dead-letter-exchange' => ['S', 'typo3.direct'],
                        'x-dead-letter-routing-key' => ['S', 'failed-direct']
                    ]
                ]
            ],
            [
                'testqueue02',
                [
                    'queue' => 'testqueue02',
                    'passive' => false,
                    'durable' => false,
                    'exclusive' => false,
                    'auto_delete' => true,
                    'arguments' => [
                        'x-dead-letter-exchange' => ['S', 'typo3.direct'],
                        'x-dead-letter-routing-key' => ['S', 'failed-direct']
                    ]
                ]
            ],
            [
                'testqueue03',
                [
                    'queue' => 'testqueue03',
                    'passive' => false,
                    'durable' => true,
                    'exclusive' => true,
                    'auto_delete' => true,
                    'arguments' => [
                        'x-dead-letter-exchange' => ['S', 'typo3.direct'],
                        'x-dead-letter-routing-key' => ['S', 'failed-direct']
                    ]
                ]
            ],
            [
                'testqueue04',
                [
                    'queue' => 'testqueue04',
                    'passive' => false,
                    'durable' => true,
                    'exclusive' => false,
                    'auto_delete' => false,
                    'arguments' => [
                        'x-dead-letter-exchange' => ['S', 'typo3.direct'],
                        'x-dead-letter-routing-key' => ['S', 'failed-direct']
                    ]
                ]
            ],
            [
                'testqueue05',
                [
                    'queue' => 'testqueue05',
                    'passive' => false,
                    'durable' => true,
                    'exclusive' => false,
                    'auto_delete' => true,
                    'arguments' => null
                ]
            ],
            [
                'testqueue06',
                [
                    'queue' => 'testqueue06',
                    'passive' => true,
                    'durable' => true,
                    'exclusive' => true,
                    'auto_delete' => true,
                    'arguments' => [
                        'x-dead-letter-exchange' => ['S', 'typo3.direct'],
                        'x-dead-letter-routing-key' => ['S', 'failed-direct']
                    ]
                ]
            ]
        ];
    }

    /**
     * @param string $queue
     * @param bool $expected
     * @test
     * @dataProvider queueOverrideExistsReturnsCorrectValueDataProvider
     */
    public function queueOverrideExistsReturnsCorrectValue($queue, $expected)
    {
        $result = $this->callInaccessibleMethod($this->subject, 'queueOverrideExists', $queue);
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public function queueOverrideExistsReturnsCorrectValueDataProvider()
    {
        return [
            ['default', false],
            ['mail', false],
            ['api', false],
            ['testqueue01', true],
            ['testqueue02', true],
            ['testqueue03', true],
            ['testqueue04', true],
            ['testqueue05', true],
            ['testqueue06', true],
        ];
    }

    /**
     * @test
     */
    public function getDefaultRoutingKeyReturnsCorrectValue()
    {
        $result = $this->callInaccessibleMethod($this->subject, 'getDefaultRoutingKey', 'failed');
        $this->assertEquals('failed-direct', $result);
    }

    protected function tearDown()
    {
        parent::tearDown();
        unset($this->subject);
    }

}