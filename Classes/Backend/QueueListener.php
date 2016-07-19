<?php
namespace TYPO3Incubator\Jobqueue\Backend;


interface QueueListener
{

    /**
     * @param string $queue
     * @param callable $cb
     * @return mixed
     */
    public function startListening($queue, $cb);

    /**
     * @param string $queue
     * @return mixed
     */
    public function stopListening($queue);

    /**
     * @param bool $blocking
     * @param callable $callable
     * @return
     */
    public function wait($blocking = false, $callable = null);

    /**
     * @param int $limit
     * @return void
     */
    public function setMessageLimit($limit);

}