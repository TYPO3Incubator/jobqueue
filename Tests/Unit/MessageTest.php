<?php
namespace TYPO3Incubator\Jobqueue\Tests\Unit;

use TYPO3\CMS\Core\Tests\UnitTestCase;

class MessageTest extends UnitTestCase
{

    /**
     * @var string
     */
    protected $handler = \TYPO3Incubator\Jobqueue\Handler\ExampleJobHandler::class . '->sleep';

    /**
     * @var array
     */
    protected $data = [
        'duration' => 5
    ];

    /**
     * @var int
     */
    protected $nextexecution = 1469561788;

    /**
     * @var string
     */
    protected $encoded = '"{\\"handler\\":\\"TYPO3Incubator\\\\\\\\Jobqueue\\\\\\\\Handler\\\\\\\\ExampleJobHandler->sleep\\",\\"data\\":{\\"duration\\":5},\\"attempts\\":0,\\"nextexecution\\":1469561788}"';

    /**
     * @var \TYPO3Incubator\Jobqueue\Message
     */
    protected $subject;

    protected function setUp()
    {
        parent::setUp();

        $this->subject = new \TYPO3Incubator\Jobqueue\Message($this->handler, $this->data);
    }

    /**
     * @test
     */
    public function initialValuesAreSetCorrectly()
    {
        $this->assertEquals($this->handler, $this->subject->getHandler());
        $this->assertEquals($this->data, $this->subject->getData());
    }

    /**
     * @test
     */
    public function defaultValuesAreSet()
    {
        $this->assertEquals(0, $this->subject->getAttempts(), 'attempts are initially set to 0');
        $this->assertNotEmpty($this->subject->getNextExecution(), 'nextexecution has a default value');
    }

    /**
     * @test
     * @dataProvider metaValuesDataProvider
     */
    public function metaValues($key, $value)
    {
        $this->subject->setMeta($key, $value);
        $this->assertEquals($value, $this->subject->getMeta($key));
    }

    /**
     * @return array
     */
    public function metaValuesDataProvider()
    {
        return [
            [
                'key.int',
                5
            ],
            [
                'key.string',
                'string.value'
            ],
            [
                'key.null',
                null
            ],
            [
                'key.array',
                [1,2,3]
            ]
        ];
    }

    /**
     * @test
     */
    public function getterAndSetterWorkAsExpected()
    {
        $this->subject->setAttempts(3);
        $this->assertEquals(3, $this->subject->getAttempts());

        $this->subject->setNextExecution($this->nextexecution);
        $this->assertEquals($this->nextexecution, $this->subject->getNextExecution());
    }

    /**
     * @test
     */
    public function objectSerializesCorrectly()
    {
        $this->subject->setAttempts(0);
        $this->subject->setNextExecution($this->nextexecution);
        $encoded = json_encode($this->subject);
        $this->assertEquals($this->encoded, $encoded);
    }

}