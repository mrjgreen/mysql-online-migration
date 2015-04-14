<?php namespace MysqlMigrate\TableDelta\Replay;

use MysqlMigrate\TableDelta\DeltasTable;

class ReplayUpdate extends ReplayAbstract
{
    public function getDiffStatement($rowId)
    {
        $newtable = $this->getTableName();

        $deltas = $this->getDeltaTableName();

        $nonPrimaryKeyCols = $this->getNonPrimaryKeyCols();

        $assignment = implode(', ', $this->buildConditionArray($newtable, $deltas, $nonPrimaryKeyCols));

        $joinClause = $this->getReplayJoinClause();

        return sprintf('UPDATE %s, %s SET %s WHERE %s.%s = %d AND %s', $newtable, $deltas, $assignment, $deltas, DeltasTable::ID_COLUMN_NAME, $rowId, $joinClause);
    }
}