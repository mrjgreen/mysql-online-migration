<?php namespace MysqlMigrate\TableDelta\Replay;

use MysqlMigrate\DbConnection;
use MysqlMigrate\TableDelta\DeltasTable;
use MysqlMigrate\TableInterface;

class Replayer
{
    private $sourceTable;

    private $destTable;

    private $deltasTable;

    private $deleteReplayer;

    private $dbSource;

    private $dbDestination;

    public function __construct(DbConnection $dbSource, DbConnection $dbDestination, TableInterface $sourceTable, TableInterface $destTable, TableInterface $deltasTable)
    {
        $this->destTable = $destTable;

        $this->sourceTable = $sourceTable;

        $this->deltasTable = $deltasTable;

        $this->dbSource = $dbSource;

        $this->dbDestination = $dbDestination;

        $this->deleteReplayer = new ReplayDelete($this->destTable, $this->deltasTable);
    }

    /**
     * @param $row
     */
    protected function deleteRow($row)
    {
        list($replaySql, $bind) = $this->deleteReplayer->getDiffStatement($row);

        $this->dbDestination->query($replaySql, $bind);
    }


    public function replayInsertsAndUpdates(\SplFileInfo $file)
    {
        $primaryKey = implode(',', $this->sourceTable->getPrimaryKey());

        $deltas = $this->deltasTable->getName();
        $main = $this->sourceTable->getName();

        $this->dbSource->selectIntoOutfile("$main JOIN $deltas USING($primaryKey)", $file, array($main . '.*'));

        $this->dbDestination->loadDataInfile($this->destTable->getName(), $file, 'REPLACE');
    }

    /**
     * @param null $transactionBatchSize
     */
    public function replayDeletes($transactionBatchSize = null)
    {
        $i = 0;

        $results = $this->getDeletes();

        if (!is_null($transactionBatchSize))
        {
            $this->dbDestination->query('BEGIN TRANSACTION');
        }

        foreach($results as $row)
        {

            $this->deleteRow($row);

            if (!is_null($transactionBatchSize) && ++$i == $transactionBatchSize)
            {
                $this->dbDestination->query('COMMIT');
                $this->dbDestination->query('BEGIN TRANSACTION');

                $i = 0;
            }
        }

        if (!is_null($transactionBatchSize))
        {
            $this->dbDestination->query('COMMIT');
        }
    }

    private function getDeletes()
    {
        $query = sprintf('SELECT * FROM %s WHERE %s = ?', $this->deltasTable->getName(), DeltasTable::TYPE_COLUMN_NAME);

        return $this->dbSource->query($query, array(DeltasTable::TYPE_DELETE));
    }
}