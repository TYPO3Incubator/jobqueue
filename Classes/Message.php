<?php
namespace TYPO3Incubator\Jobqueue;


class Message implements \JsonSerializable
{

    /**
     * @var string
     */
    protected $handler;

    /**
     * @var array
     */
    protected $data;

    /**
     * @var int
     */
    protected $nextExecution;

    /**
     * @var int
     */
    protected $attempts = 0;

    /**
     * @var array
     */
    protected $meta = [];


    /**
     * Message constructor.
     * @param string $handler
     * @param array $data
     */
    public function __construct($handler, $data)
    {
        $this->handler = $handler;
        $this->data = $data;
    }

    public function getHandler()
    {
        return $this->handler;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getMeta($key = null, $default = null)
    {
        if($key !== null) {
            return isset($this->meta[$key]) ? $this->meta[$key] : $default;
        }
        return $this->meta;
    }

    public function setMeta($key, $value)
    {
        $this->meta[$key] = $value;
        return $this;
    }

    /**
     * @param int $attempts
     * @return Message
     */
    public function setAttempts($attempts)
    {
        $this->attempts = $attempts;
        return $this;
    }

    /**
     * @return int
     */
    public function getAttempts()
    {
        return $this->attempts;
    }

    /**
     * @param int $nextExecution
     * @return Message
     */
    public function setNextExecution($nextExecution)
    {
        $this->nextExecution = $nextExecution;
        return $this;
    }

    /**
     * @return int
     */
    public function getNextExecution()
    {
        return $this->nextExecution;
    }


    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    function jsonSerialize()
    {
        return json_encode([
            'handler' => $this->handler,
            'data' => $this->data,
            'attempts' => $this->attempts,
            'nextexecution' => $this->nextExecution
        ]);
    }

}
