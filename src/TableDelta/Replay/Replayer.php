<?php namespace MysqlMigrate\TableDelta\Replay;

use MysqlMigrate\DbConnection;
use MysqlMigrate\TableDelta\DeltasTable;
use MysqlMigrate\TableInterface;

class Replayer
{
    private $table;

    private $deltasTable;

    private $diffReplayers;

    private $dbSource;

    private $dbDestination;

    public function __construct(DbConnection $dbSource, DbConnection $dbDestination, TableInterface $table, TableInterface $deltasTable)
    {
        $this->table = $table;

        $this->deltasTable = $deltasTable;

        $this->dbSource = $dbSource;

        $this->dbDestination = $dbDestination;
    }

    /**
     * @return ReplayInterface
     */
    private function getDiffReplayer($type)
    {
        $this->diffReplayers or $this->diffReplayers = array(
            DeltasTable::TYPE_INSERT => new ReplayInsert($this->table, $this->deltasTable),
            DeltasTable::TYPE_DELETE => new ReplayDelete($this->table, $this->deltasTable),
            DeltasTable::TYPE_UPDATE => new ReplayUpdate($this->table, $this->deltasTable),
            DeltasTable::TYPE_REPLACE => new ReplayReplace($this->table, $this->deltasTable),
        );

        return $this->diffReplayers[$type];
    }

    /**
     * Check that replay command has affected exactly one row
     *
     * @param \PDOStatement $statement
     * @param $replay_sql
     * @param $bind
     */
    protected function validateReplay(\PDOStatement $statement, $replay_sql, $bind)
    {
        $count = $statement->rowCount();

        if ($count !== 1)
        {
            $bind = print_r($bind, 1);

            throw new \RuntimeException("Replay command [$replay_sql, $bind] affected $count rows instead of 1 row");
        }
    }

    /**
     * @param $type
     * @param $row
     */
    protected function replayRow($type, $row)
    {
        list($replaySql, $bind) = $this->getDiffReplayer($type)->getDiffStatement($row);

        $stmt = $this->dbDestination->query($replaySql, $bind);

        $this->validateReplay($stmt, $replaySql, $bind);
    }

    /**
     * @param null $transactionBatchSize
     * @return int
     */
    public function replayChanges($transactionBatchSize = null)
    {
        $lastId = 0;

        $i = 0;

        do
        {
            $results = $this->getDeltaResultSet($lastId, $transactionBatchSize ?: 10000);

            $count = $results->rowCount();

            if (!is_null($transactionBatchSize))
            {
                $this->dbDestination->query('BEGIN TRANSACTION');
            }

            foreach($results as $row)
            {
                $i++;

                $this->replayRow($row[DeltasTable::TYPE_COLUMN_NAME], $row);

                $lastId = $row[DeltasTable::ID_COLUMN_NAME];
            }

            if (!is_null($transactionBatchSize))
            {
                $this->dbDestination->query('COMMIT');
            }

        }while($count > 0);

        return $i;
    }

    private function getDeltaResultSet($lastId, $batchSize)
    {
        $query = sprintf('SELECT * FROM %s WHERE %s > ? ORDER BY %s LIMIT %s', $this->deltasTable->getName(), DeltasTable::ID_COLUMN_NAME, DeltasTable::ID_COLUMN_NAME, $batchSize);

        return $this->dbSource->query($query, array($lastId));
    }
}