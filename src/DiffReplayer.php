<?php namespace MysqlMigrate;

use MysqlMigrate\TableDelta\DeltasTable;
use MysqlMigrate\TableDelta\Replay\ReplayDelete;
use MysqlMigrate\TableDelta\Replay\ReplayInsert;
use MysqlMigrate\TableDelta\Replay\ReplayInterface;
use MysqlMigrate\TableDelta\Replay\ReplayReplace;
use MysqlMigrate\TableDelta\Replay\ReplayUpdate;

class DiffReplayer
{
    private $table;

    private $deltasTable;

    private $diffReplayers;

    public function __construct(TableInterface $table, TableInterface $deltasTable)
    {
        $this->table = $table;

        $this->deltasTable = $deltasTable;
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

    // check that replay command has affected exactly one row
    protected function validateReplay(PDOStatement $statement, $replay_sql)
    {
        $count = $statement->rowCount();

        if ($count !== 1)
        {
            throw new RuntimeException("Replay command [$replay_sql] affected $count rows instead of 1 row");
        }
    }

    // Row has ID that can be used to look up into deltas table
    // to find PK of the row in the newtable to delete
    protected function replayRow($type, $rowId)
    {
        $replaySql = $this->getDiffReplayer($type)->getDiffStatement($rowId);

        $stmt = $this->db->query($replaySql);

        $this->validateReplay($stmt, $replaySql);
    }

    public function replayChanges($transactionBatchSize = null)
    {
        $query = sprintf('select %s, %s from %s order by %s', DeltasTable::ID_COLUMN_NAME, DeltasTable::TYPE_COLUMN_NAME, $this->deltasTable->getName(), DeltasTable::ID_COLUMN_NAME);

        $result = $this->db->query($query);

        $i = 0;
        $inserts = 0;
        $deletes = 0;
        $updates = 0;

        if(!is_null($transactionBatchSize))
        {
            $this->db->query('BEGIN TRANSACTION');
        }

        while ($row = $result->fetch()) {

            ++$i;

            if (!is_null($transactionBatchSize) && ($i % $transactionBatchSize == 0))
            {
                $this->db->query('COMMIT');
            }

            $this->replayRow($row[DeltasTable::TYPE_COLUMN_NAME], $row[DeltasTable::ID_COLUMN_NAME]);
        }

        if (!is_null($transactionBatchSize))
        {
            $this->db->query('COMMIT');
        }

        return array(
            $i,
            $inserts,
            $deletes,
            $updates
        );
    }
}