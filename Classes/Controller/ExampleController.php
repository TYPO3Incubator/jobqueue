<?php
namespace TYPO3Incubator\Jobqueue\Controller;

use TYPO3Incubator\Jobqueue\QueueManager;

class ExampleController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{

    /**
     * @var \TYPO3\CMS\Core\Log\Logger
     */
    protected $logger;

    /**
     * @var array
     */
    protected $handlerArguments = [
        \TYPO3Incubator\Jobqueue\Handler\ExampleJobHandler::class.'->sleep' => [
            'duration' => 'int'
        ],
        \TYPO3Incubator\Jobqueue\Handler\ExampleJobHandler::class.'->resize' => [
            'image' => 'file'
        ]
    ];

    public function initializeAction()
    {
        parent::initializeAction();
        $this->logger = $this->objectManager->get(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
    }

    public function indexAction()
    {
        $backends = $this->getBackends();
        $handlers = [];
        foreach($this->handlerArguments as $key => $value) {
            $handlers[$key] = $key;
        }
        $this->view->assign('backends', $backends);
        $this->view->assign('handler', $handlers);
    }

    /**
     * @param string $backend
     * @param string $handler
     * @param string $queue
     * @param int $count
     */
    public function queueAction($backend, $handler, $queue, $count = 1)
    {
        /** @var \TYPO3Incubator\Jobqueue\QueueManager $qm */
        $qm = $this->objectManager->get(\TYPO3Incubator\Jobqueue\QueueManager::class);
        $queueInterface = $qm->get($backend);
        $data = $this->getHandlerData($handler);
        if($handler !== \TYPO3Incubator\Jobqueue\Handler\ExampleJobHandler::class.'->resize') {
            for ($x = 0; $x < $count; $x++) {
                $queueInterface->setQueue($queue)->queue($handler, $data);
            }
        } else {
            $sizes = [];
            for($i = 100; $i <= 800; $i += 10) {
                $sizes[] = [$i, $i];
            }
            foreach($sizes as $dimensions) {
                $fData = $data;
                $fData['width'] = $dimensions[0];
                $fData['height'] = $dimensions[1];
                $queueInterface->setQueue($queue)->queue($handler, $fData);
            }
        }
        $this->addFlashMessage('Added the handler '.$handler.' to the queue '.$queue.' '.$count.' times', 'Queuing Info');
        $this->redirect('index');
    }

    /**
     *
     */
    public function infoAction()
    {
        if($this->request->hasArgument('backend') && $this->request->hasArgument('queue')) {
            $backendIdentifier = $this->request->getArgument('backend');
            $queue = $this->request->getArgument('queue');
            /** @var \TYPO3Incubator\Jobqueue\QueueManager $qm */
            $qm = $this->objectManager->get(\TYPO3Incubator\Jobqueue\QueueManager::class);

            $backend = $qm->getBackend($backendIdentifier);

            $this->view->assignMultiple([
                'selectedBackend' => $backendIdentifier,
                'selectedQueue' => $queue,
                'count' => $backend->count($queue)
            ]);
        }
        
        $this->view->assign('backends', $this->getBackends());
        
    }

    protected function getBackends()
    {
        $backends = [];
        /** @var QueueManager $qm */
        $qm = $this->objectManager->get(\TYPO3Incubator\Jobqueue\QueueManager::class);
        //-> tmp
        #$queue = $qm->getBackend('default');
        #$msg = $queue->get('internal');
        #$queue->setQueue('internal')->queue(\TYPO3Incubator\Jobqueue\Handler\ExampleJobHandler::class.'->sleep', ['duration' => 3], 0);
        //-> tmp
        if(isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['queue']['configuration'])) {
            foreach($GLOBALS['TYPO3_CONF_VARS']['SYS']['queue']['configuration'] as $key => $val) {
                $backends[$key] = $key;
                try {
                    $qm->get($key);
                } catch (\Exception $e) {
                    unset($backends[$key]);
                }
            }
        }
        return $backends;
    }

    protected function getHandlerData($handler)
    {
        $data = [];
        if(isset($this->handlerArguments[$handler])) {
            foreach($this->handlerArguments[$handler] as $key => $type) {
                $value = $this->request->getArgument($key);
                switch($type) {
                    case 'int':
                        $data[$key] = (int) $value;
                        break;
                    case 'file':
                        if(is_array($value) && !empty($value['name']) && !empty($value['tmp_name'])) {
                            /** @var \TYPO3\CMS\Core\Resource\StorageRepository $storageRepo */
                            $storageRepo = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\StorageRepository::class);
                            // use default storage for now (fileadmin)
                            $resourceStorage = $storageRepo->findByUid(1);
                            if(! $resourceStorage->hasFolderInFolder('tx_jobqueue', $resourceStorage->getRootLevelFolder())) {
                                $resourceStorage->createFolder('tx_jobqueue');
                            }
                            $folder = $resourceStorage->getFolder('tx_jobqueue');
                            $file = $resourceStorage->addUploadedFile($value, $folder);
                            $data[$key] = $file->getIdentifier();
                        }
                        break;
                    default:
                        $data[$key] = $value;
                        break;
                }
            }
        }
        return $data;
    }

}
