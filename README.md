# TYPO3 CMS
[![Build Status](https://travis-ci.org/TYPO3Incubator/jobqueue.svg?branch=master)](https://travis-ci.org/TYPO3Incubator/jobqueue) [![Code Climate](https://codeclimate.com/github/TYPO3Incubator/jobqueue/badges/gpa.svg)](https://codeclimate.com/github/TYPO3Incubator/jobqueue)
## Job Queueing System

## Quick Start

### Queueing Jobs

```php
/** @var QueueManager $queueManager */
$queueManager = $this->objectManager->get(\TYPO3Incubator\Jobqueue\QueueManager::class);
/** @var \TYPO3Incubator\Jobqueue\Frontend\Queue $queueFrontend */
$queueFrontend = $queueManager->get('default');
$queueFrontend->setQueue('queuename')
              ->queue('handlerRef', ['data' => 'forHandler']);
```

### Handler References

Class and method

`TYPO3Incubator\Handler\ExampleJobHandler::sleep`

Class and static method

`TYPO3Incubator\Handler\ExampleJobHandler->sleep`

### Queue Worker

`typo3/sysext/core/bin/typo3 jobqueue:work --mode=s backend queuename`
 
For the example above that would be:

`typo3/sysext/core/bin/typo3 jobqueue:work --mode=s default queuename`

Further information

`typo3/sysext/core/bin/typo3 help jobqueue:work`

### Queue Listener

`typo3/sysext/core/bin/typo3 jobqueue:listen --limit=5 --graceful-shutdown backend queuename`
 
For the example above that would be:

`typo3/sysext/core/bin/typo3 jobqueue:listen --limit=5 --graceful-shutdown default queuename`

Further information

`typo3/sysext/core/bin/typo3 help jobqueue:listen`

