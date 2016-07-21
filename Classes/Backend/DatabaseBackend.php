<?php
namespace TYPO3Incubator\Jobqueue\Backend;


class DatabaseBackend implements BackendInterface
{

    /**
     * @var string
     */
    protected $tableName = 'jobqueue_job';

    /**
     * @var \TYPO3\CMS\Core\Database\Query\QueryBuilder
     */
    protected $queryBuilder;

    /**
     * @var \TYPO3\CMS\Core\Database\Connection
     */
    protected $connection;

    /**
     * @var \TYPO3\CMS\Core\Database\ConnectionPool
     */
    protected $connectionPool;

    /**
     * @var string
     */
    protected $queue;

    protected $insertQuery;

    protected $selectQuery;

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
        /** @var \TYPO3\CMS\Core\Database\ConnectionPool $connectionPool */
        $this->connectionPool = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class);
        $this->queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->tableName);
        $this->connection = $this->connectionPool->getConnectionForTable($this->tableName);
        $this->queue = $options['queue'];
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
        $this->queryBuilder->insert($this->tableName)->values($values)->execute();
        $uid = $this->connection->lastInsertId();
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
        $this->queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->tableName);
        $row = $this->queryBuilder->select('*')
            ->from($this->tableName)
            ->where(
                $this->queryBuilder->expr()->eq('queue', $this->connection->quote($queue)),
                $this->queryBuilder->expr()->lte('nextexecution', time()),
                $this->queryBuilder->expr()->eq('locked', 0)
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
        $this->connection->delete($this->tableName, ['uid' => $uid]);
    }

    /**
     * @param \TYPO3Incubator\Jobqueue\Message $message
     * @return mixed
     */
    public function update($message)
    {
        $uid = $this->getUidFromMessage($message);
        $values = $this->getValuesFromMessage($message);
        $this->connection->update($this->tableName, $values, ['uid' => $uid]);
        $this->freeRecord($uid);
    }

    /**
     * @param string $queue
     * @return int
     */
    public function count($queue)
    {
        return $this->connectionPool->getQueryBuilderForTable($this->tableName)->count('*')
            ->from($this->tableName)
            ->where(
                $this->queryBuilder->expr()->eq('queue', $this->connection->quote($queue)),
                $this->queryBuilder->expr()->lte('nextexecution', time()),
                $this->queryBuilder->expr()->eq('locked', 0)
            )
            ->execute()
            ->fetchColumn(0);
    }

    protected function lockRecord($uid)
    {
        $this->queryBuilder->update($this->tableName)
            ->where($this->queryBuilder->expr()->eq('uid', $uid))
            ->set('locked', 1)
            ->execute();
    }

    protected function freeRecord($uid)
    {
        $this->queryBuilder->update($this->tableName)
            ->where($this->queryBuilder->expr()->eq('uid', $uid))
            ->set('locked', 0)
            ->execute();
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

    /**
     * @return \TYPO3\CMS\Core\Database\Query\QueryBuilder
     */
    protected function getSelectQuery()
    {
        return $this->queryBuilder->select('*')
            ->from($this->tableName)
            ->where(
                $this->queryBuilder->expr()->eq('locked', 0),
                $this->queryBuilder->expr()->lte('nextexecution', time())
            )
            ->orderBy('priority', 'ASC');
    }

    protected function getInsertQuery()
    {
        return $this->queryBuilder->insert($this->tableName);
    }

}