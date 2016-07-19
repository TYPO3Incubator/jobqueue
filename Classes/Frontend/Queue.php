<?php
namespace TYPO3Incubator\Jobqueue\Frontend;
class Queue
{

    /**
     * @var \TYPO3Incubator\Jobqueue\Backend\BackendInterface
     */
    protected $connection;

    /**
     * @var string
     */
    protected $queue;

    /**
     * Queue constructor.
     * @param \TYPO3Incubator\Jobqueue\Backend\BackendInterface $connection
     * @param string $defaultQueue
     */
    public function __construct($connection, $defaultQueue)
    {
        $this->connection = $connection;
        $this->queue = $defaultQueue;
    }

    /**
     *
     * @param string $queue
     * @return $this
     */
    public function setQueue($queue)
    {
        $this->queue = $queue;
        return $this;
    }

    /**
     * @param string $handler
     * @param array $data
     * @param int $delay
     * @return $this
     */
    public function queue($handler, $data, $delay = 0)
    {
        $message = $this->buildMessage($handler, $data, $delay);
        $this->connection->add($this->queue, $message);
        return $this;
    }

    protected function buildMessage($handler, $data, $delay)
    {
        if(! \TYPO3Incubator\Jobqueue\Utility::validHandler($handler)) {
            throw new \InvalidArgumentException("invalid handler reference '{$handler}'");
        }
        $nextExecution = time();
        if($delay !== 0) {
            $nextExecution += $delay;
        }
        return (new \TYPO3Incubator\Jobqueue\Message($handler, $data))
            ->setAttempts(0)
            ->setNextExecution($nextExecution);
    }

}