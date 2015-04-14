<?php namespace MysqlMigrate\TableDelta\Replay;

use MysqlMigrate\TableDelta\DeltasTable;

class ReplayDelete extends ReplayAbstract
{
    public function getDiffStatement($rowId)
    {
        $newtable = $this->getTableName();

        $deltas = $this->getDeltaTableName();

        $joinClause = $this->getReplayJoinClause();

        return sprintf('DELETE %s FROM %s, %s WHERE %s.%s = %d AND %s', $newtable, $newtable, $deltas, $deltas, DeltasTable::ID_COLUMN_NAME, $rowId, $joinClause);
    }
}