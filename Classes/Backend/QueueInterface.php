<?php
namespace TYPO3Incubator\Jobqueue\Backend;

/**
 * Interface Defaultbackend
 * @package TYPO3Incubator\Jobqueue\Backend
 * @author Dustin Kinney <dustin@kinney.ws>
 */
interface QueueInterface
{

    /**
     * @param string $handler Class which should handle the Job
     * @param array $payload
     */
    public function queue($handler, $payload);
    
    public function getJob();

}
