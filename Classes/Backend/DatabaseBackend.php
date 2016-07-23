<?php
namespace TYPO3Incubator\Jobqueue\Backend;


class DatabaseBackend implements BackendInterface
{

    /**
     * @var string
     */
    protected $tableName;

    /**
     * @var \TYPO3\CMS\Core\Database\ConnectionPool
     */
    protected $connectionPool;

    /**
     * @var array
     */
    protected $defaultValues = [
        'locked' => 0,
        'queue' => '',
        'attempts' => 0,
        'nextexecution' => 0,
        'payload' => ''
    ];

    /**
     * DatabaseBackend constructor.
     * @param array $options
     */
    public function __construct($options)
    {
        if (empty($options['table'])) {
           throw new \InvalidArgumentException('You must configure the table to use'.var_export($options, true));
        }
        /** @var \TYPO3\CMS\Core\Database\ConnectionPool $connectionPool */
        $this->connectionPool = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class);
        $this->tableName = $options['table'];
    }


    /**
     * @param string $queue
     * @param \TYPO3Incubator\Jobqueue\Message $message
     * @return mixed
     */
    public function add($queue, $message)
    {
        $values = $this->defaultValues;
        $values['queue'] = $queue;
        \TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule($values,
            $this->getValuesFromMessage($message));
        $this->getQueryBuilder()->insert($this->tableName)->values($values)->execute();
        $uid = $this->getConnection()->lastInsertId();
        $message->setMeta('db.uid', $uid);
        $message->setMeta('db.queue', $queue);
    }

    /**
     * Gets the next message from the queue. By default the message will be
     * locked so nobody else processes the same message in parallel.
     *
     * @param string $queue
     * @param bool $lock
     * @return null|\TYPO3Incubator\Jobqueue\Message
     */
    public function get($queue, $lock = true)
    {
        $row = $this->getQueryBuilder()->select('*')
            ->from($this->tableName)
            ->where(
                $this->getQueryBuilder()->expr()->eq('queue', $this->getConnection()->quote($queue)),
                $this->getQueryBuilder()->expr()->lte('nextexecution', time()),
                $this->getQueryBuilder()->expr()->eq('locked', 0)
            )
            ->execute()
            ->fetch();
        if ($row === false || $row === null) {
            return null;
        }
        $msg = $this->getMessageFromRow($row);
        if ($lock === true) {
            $this->lockRecord($row['uid']);
        }
        return $msg;
    }

    /**
     * @param \TYPO3Incubator\Jobqueue\Message $message
     * @return mixed
     */
    public function remove($message)
    {
        $uid = $this->getUidFromMessage($message);
        $this->getConnection()->delete($this->tableName, ['uid' => $uid]);
    }

    /**
     * @param \TYPO3Incubator\Jobqueue\Message $message
     * @return mixed
     */
    public function update($message)
    {
        $uid = $this->getUidFromMessage($message);
        $values = $this->getValuesFromMessage($message);
        $this->getConnection()->update($this->tableName, $values, ['uid' => $uid]);
        $this->freeRecord($uid);
    }

    /**
     * @param string $queue
     * @return int
     */
    public function count($queue)
    {
        return $this->getQueryBuilder()->count('*')
            ->from($this->tableName)
            ->where(
                $this->getQueryBuilder()->expr()->eq('queue', $this->getConnection()->quote($queue)),
                $this->getQueryBuilder()->expr()->lte('nextexecution', time()),
                $this->getQueryBuilder()->expr()->eq('locked', 0)
            )
            ->execute()
            ->fetchColumn(0);
    }

    /**
     * @param \TYPO3Incubator\Jobqueue\Message $message
     * @return mixed
     */
    public function failed($message)
    {
        $uid = $this->getUidFromMessage($message);
        $values = $this->getValuesFromMessage($message);
        $values['queue'] = 'failed';
        $this->getConnection()->update($this->tableName, $values, ['uid' => $uid]);
        $this->freeRecord($uid);
    }

    protected function lockRecord($uid)
    {
        $this->getQueryBuilder()->update($this->tableName)
            ->where($this->getQueryBuilder()->expr()->eq('uid', $uid))
            ->set('locked', 1)
            ->execute();
    }

    protected function freeRecord($uid)
    {
        $this->getQueryBuilder()->update($this->tableName)
            ->where($this->getQueryBuilder()->expr()->eq('uid', $uid))
            ->set('locked', 0)
            ->execute();
    }

    /**
     * @return \TYPO3\CMS\Core\Database\Connection
     */
    protected function getConnection()
    {
        return $this->connectionPool->getConnectionForTable($this->tableName);
    }

    /**
     * @return \TYPO3\CMS\Core\Database\Query\QueryBuilder
     */
    protected function getQueryBuilder()
    {
        return $this->connectionPool->getQueryBuilderForTable($this->tableName);
    }

    protected function getUidFromMessage(\TYPO3Incubator\Jobqueue\Message $message)
    {
        $uid = $message->getMeta('db.uid', null);
        if ($uid === null) {
            throw new \InvalidArgumentException('this message was not retrieved through the database backend');
        }
        return $uid;
    }

    protected function getValuesFromMessage(\TYPO3Incubator\Jobqueue\Message $message)
    {
        $values = [
            'attempts' => $message->getAttempts(),
            'nextexecution' => $message->getNextExecution(),
            'payload' => json_encode([
                'handler' => $message->getHandler(),
                'data' => $message->getData()
            ]),
        ];
        return $values;
    }

    protected function getMessageFromRow($row)
    {
        $payload = json_decode($row['payload'], true);
        return (new \TYPO3Incubator\Jobqueue\Message($payload['handler'], $payload['data']))
            ->setAttempts($row['attempts'])
            ->setNextExecution($row['nextexecution'])
            ->setMeta('db.uid', $row['uid'])
            ->setMeta('db.queue', $row['queue']);
    }

}