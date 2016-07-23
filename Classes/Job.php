<?php
namespace TYPO3Incubator\Jobqueue;

class Job
{

    const STATE_DELETED = 0;
    const STATE_RELEASED = 1;

    /**
     * @var Message
     */
    private $message;

    /**
     * @var int
     */
    private $state;

    /**
     * Jobs constructor.
     * @param Message $message
     */
    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    /**
     * @return int
     */
    public function attempts()
    {
        return $this->message->getAttempts();
    }

    /**
     * Release the job back on to the queue
     * @param int $delay
     */
    public function release($delay = 0)
    {
        $this->message->setNextExecution(time() + $delay);
        $this->state = self::STATE_RELEASED;
    }

    /**
     * Processing is finished. Remove the job from the queue.
     * @return void
     */
    public function delete()
    {
        $this->state = self::STATE_DELETED;
    }

    /**
     * @return bool
     */
    public function isDeleted()
    {
        return $this->state === self::STATE_DELETED;
    }

    /**
     * @return bool
     */
    public function isReleased()
    {
        return $this->state === self::STATE_RELEASED;
    }

    /**
     * @return bool
     */
    public function shouldBeRequeued()
    {
        return $this->state === self::STATE_RELEASED;
    }

}