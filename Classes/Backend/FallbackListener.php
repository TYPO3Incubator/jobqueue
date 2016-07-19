<?php
/**
 * Created by PhpStorm.
 * User: dustin
 * Date: 12.07.16
 * Time: 22:08
 */

namespace TYPO3Incubator\Jobqueue;


class FallbackListener implements \TYPO3Incubator\Jobqueue\Backend\QueueListener
{

    /**
     * @var \TYPO3Incubator\Jobqueue\Backend\BackendInterface
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
     * @param Backend\BackendInterface $backend
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
        // not needed in fallback mode
    }

    /**
     * @param bool $blocking
     */
    public function wait($blocking = false)
    {
        $msg = $this->getMessage();
        if($blocking === true) {
            while($msg === null) {
                usleep(500);
                $msg = $this->getMessage();
            }
        }
        if($msg instanceof Message) {
            call_user_func($this->cb, $msg);
        }
    }

    /**
     *
     */
    protected function getMessage()
    {
        return $this->backend->get($this->queue);
    }

}