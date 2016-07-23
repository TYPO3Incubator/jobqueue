<?php
namespace TYPO3Incubator\Jobqueue;


class Worker
{

    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManager
     */
    protected $objectManager;

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
     * @throws ProcessingException
     */
    public function run()
    {
        $handler = $this->message->getHandler();
        if (!\TYPO3Incubator\Jobqueue\Utility::isStaticMethodHandler($handler)) {
            $handler = \TYPO3Incubator\Jobqueue\Utility::extractHandlerClassAndMethod($handler);
        }

        if (is_array($handler)) {
            $obj = $this->objectManager->get($handler['class']);
            $handler = [$obj, $handler['method']];
        }

        $this->message->setAttempts($this->message->getAttempts() + 1);
        $job = new Job($this->message);

        try {
            call_user_func($handler, $this->message->getData(), $job);
        } catch (\Exception $e) {
            throw new ProcessingException('En exception occured when processing a message', 0, $e);
        }

        return $job;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Object\ObjectManager $objectManager
     */
    public function injectObjectManager(\TYPO3\CMS\Extbase\Object\ObjectManager $objectManager)
    {
        $this->objectManager = $objectManager;
    }
}