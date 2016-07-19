<?php
namespace TYPO3Incubator\Jobqueue;


class Worker
{

    /**
     * @var Message
     */
    private $message;

    public function __construct(\TYPO3Incubator\Jobqueue\Message $message)
    {
        $this->message = $message;
    }

    /**
     * @return Job
     */
    public function run()
    {
        $handler = $this->message->getHandler();
        if (! \TYPO3Incubator\Jobqueue\Utility::isStaticMethodHandler($handler)) {
            $handler = \TYPO3Incubator\Jobqueue\Utility::extractHandlerClassAndMethod($handler);
        }

        if(is_array($handler)) {
            $obj = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance($handler['class']);
            $handler = [$obj, $handler['method']];
        }

        $this->message->setAttempts($this->message->getAttempts()+1);
        $job = new Job($this->message);

        try {
            call_user_func($handler, $this->message->getData(), $job);
        } catch (\Exception $e) {
            // @todo think about it...
        }

        /*
        if(! $job->isRejected() && ! $job->isResolved()) {
            $handler = $this->message->getHandler();
            throw new \LogicException("The handler '{$handler}' did not resolve or reject his job!");
        }
        */

        return $job;
    }
    
    
}