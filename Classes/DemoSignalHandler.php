<?php
namespace TYPO3Incubator\Jobqueue;

use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Folder;

class DemoSignalHandler
{

    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManager
     */
    protected $objectManager;

    public function queueDemoTasks(FileInterface $file, Folder $targetFolder)
    {
        if(preg_match('/image\/.+/', $file->getMimeType()) === 1) {
            $data = [
                'image' => $file->getCombinedIdentifier()
            ];
            /** @var \TYPO3Incubator\Jobqueue\QueueManager $qm */
            $qm = $this->objectManager->get(\TYPO3Incubator\Jobqueue\QueueManager::class);
            $queueInterface = $qm->get('default');

            // Queue some resize tasks for often used image sizes across the site
            $sizes = [];
            for ($i = 100; $i <= 800; $i += 100) {
                $sizes[] = [$i, $i];
            }
            foreach ($sizes as $dimensions) {
                $fData = $data;
                $fData['width'] = $dimensions[0];
                $fData['height'] = $dimensions[1];
                $queueInterface->setQueue('default')->queue(\TYPO3Incubator\Jobqueue\Handler\ExampleJobHandler::class.'->resize', $fData);
            }

            // queue a notification via email (fake smtp in use)
            $data['recipent'] = 'foo@bar.com';
            $data['message'] = 'a new image was uploaded!';
            $queueInterface->setQueue('default')->queue(\TYPO3Incubator\Jobqueue\Handler\ExampleJobHandler::class.'->mail', $data);
        }
    }

    /**
     * @param \TYPO3\CMS\Extbase\Object\ObjectManager $objectManager
     */
    public function injectObjectManager(\TYPO3\CMS\Extbase\Object\ObjectManager $objectManager)
    {
        $this->objectManager = $objectManager;
    }

}