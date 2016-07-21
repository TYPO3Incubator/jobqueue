<?php

namespace TYPO3Incubator\Jobqueue\Backend;

use TYPO3Incubator\Jobqueue\Message;

class FallbackListener implements QueueListener, BackendInterface
{

    /**
     * @var BackendInterface
     */
    protected $backend;

    /**
     * @var string
     */
    protected $queue;

    /**
     * @var callable
     */
    protected $cb;

    /**
     * FallbackListener constructor.
     * @param BackendInterface $backend
     */
    public function __construct(\TYPO3Incubator\Jobqueue\Backend\BackendInterface $backend)
    {
        $this->backend = $backend;
    }

    /**
     * @param string $queue
     * @param $cb
     * @return mixed
     */
    public function startListening($queue, $cb)
    {
        $this->queue = $queue;
        $this->cb = $cb;
    }

    /**
     * @param string $queue
     * @return mixed
     */
    public function stopListening($queue)
    {
        $this->queue = null;
        $this->cb = null;
    }

    /**
     * @param bool $blocking
     * @param callable $callable
     */
    public function wait($blocking = false, $callable = null)
    {
        if ($this->queue === null) {
            // we stopped listening
            return;
        }
        $msg = $this->getMessage();
        if ($blocking === true) {
            while ($msg === null) {
                usleep(100000);
                if ($callable !== null) {
                    call_user_func($callable);
                }
                $msg = $this->getMessage();
            }
        }
        if ($msg instanceof Message) {
            call_user_func($this->cb, $msg);
        }
    }

    /**
     * @return null|Message
     */
    protected function getMessage()
    {
        return $this->backend->get($this->queue);
    }

    /**
     * @param string $queue
     * @param \TYPO3Incubator\Jobqueue\Message $message
     * @return mixed
     */
    public function add($queue, $message)
    {
        $this->backend->add($queue, $message);
    }

    /**
     * Gets the next message from the queue. By default the message will be
     * locked so nobody else processes the same message in parallel.
     *
     * @param string $queue
     * @param bool $lock
     * @return null|\TYPO3Incubator\Jobqueue\Message
     */
    public function get($queue, $lock = true)
    {
        $this->backend->get($queue, $lock);
    }

    /**
     * @param \TYPO3Incubator\Jobqueue\Message $message
     * @return mixed
     */
    public function remove($message)
    {
        $this->backend->remove($message);
    }

    /**
     * @param \TYPO3Incubator\Jobqueue\Message $message
     * @return mixed
     */
    public function update($message)
    {
        $this->backend->update($message);
    }

    /**
     * @param string $queue
     * @return int
     */
    public function count($queue)
    {
        $this->backend->count($queue);
    }

    /**
     * @param int $limit
     * @return void
     */
    public function setMessageLimit($limit)
    {
        // not needed
    }
}