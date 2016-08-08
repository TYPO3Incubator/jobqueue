<?php
namespace TYPO3Incubator\Jobqueue\Handler;


class ExampleJobHandler
{

    public function sleep(array $data, \TYPO3Incubator\Jobqueue\Job $job)
    {
        $time = (int)$data['duration'];
        sleep($time);
        $job->delete();
        return;
    }

    public function resize(array $data, \TYPO3Incubator\Jobqueue\Job $job)
    {
        /** @var \TYPO3\CMS\Extbase\Object\ObjectManager $objM */
        $objM = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\ObjectManager::class);
        /** @var \TYPO3\CMS\Extbase\Service\ImageService $imageService */
        $imageService = $objM->get(\TYPO3\CMS\Extbase\Service\ImageService::class);
        $file = \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance()->getFileObjectByStorageAndIdentifier(1,
            $data['image']);
        $processingInstructions = array(
            'width' => $data['width'] . 'c',
            'height' => $data['height'],
            'minWidth' => null,
            'minHeight' => null,
            'maxWidth' => null,
            'maxHeight' => null,
            'crop' => null,
        );
        $processedImage = $imageService->applyProcessingInstructions($file, $processingInstructions);
        if ($processedImage instanceof \TYPO3\CMS\Core\Resource\ProcessedFile) {
            $job->delete();
        } else {
            if ($job->attempts() <= 2) {
                $job->release(20);
            }
        }
    }

    public function fail($data, \TYPO3Incubator\Jobqueue\Job $job)
    {
        throw new \RuntimeException('this handler fails');
    }

    public function mail($data,\TYPO3Incubator\Jobqueue\Job $job)
    {
        /** @var \TYPO3\CMS\Core\Mail\MailMessage $mail */
        $mail = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Mail\\MailMessage');

        $file = \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance()->getFileObjectByStorageAndIdentifier(1,
            $data['image']);

        $mail->setFrom('jobqueuedemo@localhost')
            ->setTo($data['recipent'])
            ->setBody('This is a demo mail. Please dont filter me :/')
            ->attach(\Swift_Attachment::fromPath($file->getForLocalProcessing()))
            ->send();
        $job->delete();
    }

}