<?php
namespace TYPO3Incubator\Jobqueue\Backend;


interface BackendInterface
{

    /**
     * @param string $queue
     * @param \TYPO3Incubator\Jobqueue\Message $message
     * @return mixed
     */
    public function add($queue, $message);

    /**
     * Gets the next message from the queue. By default the message will be
     * locked so nobody else processes the same message in parallel.
     *
     * @param string $queue
     * @param bool $lock
     * @return null|\TYPO3Incubator\Jobqueue\Message
     */
    public function get($queue, $lock = true);

    /**
     * @param \TYPO3Incubator\Jobqueue\Message $message
     * @return mixed
     */
    public function remove($message);

    /**
     * @param \TYPO3Incubator\Jobqueue\Message $message
     * @return mixed
     */
    public function update($message);

    /**
     * @param string $queue
     * @return int
     */
    public function count($queue);

}