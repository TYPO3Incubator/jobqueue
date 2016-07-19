<?php
namespace TYPO3Incubator\Jobqueue\Jobs;


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
     * @var array
     */
    protected $meta = [
        'attempts' => 0
    ];


    /**
     * Job constructor.
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
        
    }

    public function getData()
    {
        return $this->data;
    }

    public function getMeta()
    {
        return $this->meta;
    }

    public function release()
    {

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
            'data' => json_encode($this->data),
            'meta' => json_encode($this->meta)
        ]);
    }
}
