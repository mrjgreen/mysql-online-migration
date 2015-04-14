<?php namespace MysqlMigrate\TableDelta\Replay;

use MysqlMigrate\TableDelta\DeltasTable;

class ReplayInsert extends ReplayAbstract
{
    protected $keyword = 'INSERT';

    public function getDiffStatement($rowId)
    {
        $newtable = $this->getTableName();

        $deltas = $this->getDeltaTableName();

        $cols = implode(',', $this->getTableColumns());

        return sprintf($this->keyword . ' INTO %s(%s) SELECT %s FROM %s WHERE %s.%s = %d', $newtable, $cols, $cols, $deltas, $deltas, DeltasTable::ID_COLUMN_NAME, $rowId);
    }
}
