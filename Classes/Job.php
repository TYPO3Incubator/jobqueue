<?php
namespace TYPO3Incubator\Jobqueue;

class Job
{

    const STATE_REJECTED = 0;
    const STATE_RESOLVED = 1;
    const STATE_DELETED = 2;
    const STATE_RELEASED = 3;

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
        $this->message->setNextExecution(time()+$delay);
        $this->state = self::STATE_RELEASED;
    }


    /**
     * @internal
     * @return bool
     */
    public function reject()
    {
        $this->state = self::STATE_REJECTED;
        throw new \Exception('currently not supported');
    }

    /**
     * Processing is finished. Remove the job from the queue.
     */
    public function delete()
    {
        $this->state = self::STATE_DELETED;
    }

    /**
     * @internal
     * @return bool
     */
    public function resolve()
    {
        $this->state = self::STATE_RESOLVED;
        throw new \Exception('currently not supported');
    }

    public function isRejected()
    {
        return $this->state === self::STATE_REJECTED;
    }

    public function isResolved()
    {
        return $this->state === self::STATE_RESOLVED;
    }

    public function isDeleted()
    {
        return $this->state === self::STATE_DELETED;
    }

    public function isReleased()
    {
        return $this->state === self::STATE_RELEASED;
    }

    public function shouldBeRequeued()
    {
        return $this->state === self::STATE_RELEASED;
    }

}