<?php

namespace TYPO3Incubator\Jobqueue;


class RunningJob
{
    /**
     * @var Message
     */
    protected $message;
    /**
     * @var \Symfony\Component\Process\Process
     */
    protected $process;

    /**
     * RunningJob constructor.
     * @param Message $message
     * @param \Symfony\Component\Process\Process $process
     */
    public function __construct(Message $message, \Symfony\Component\Process\Process $process)
    {
        $this->message = $message;
        $this->process = $process;
    }

    /**
     * @param \Symfony\Component\Process\Process $process
     * @return RunningJob
     */
    public function setProcess($process)
    {
        $this->process = $process;
        return $this;
    }

    /**
     * @return \Symfony\Component\Process\Process
     */
    public function getProcess()
    {
        return $this->process;
    }

    /**
     * @param Message $message
     * @return RunningJob
     */
    public function setMessage($message)
    {
        $this->message = $message;
        return $this;
    }

    /**
     * @return Message
     */
    public function getMessage()
    {
        return $this->message;
    }


}