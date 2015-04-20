<?php namespace MysqlMigrate\TableDelta\Replay;

use MysqlMigrate\TableInterface;

abstract class ReplayAbstract implements ReplayInterface
{
    private $table;

    private $deltasTable;

    public function __construct(TableInterface $table, TableInterface $deltasTable)
    {
        $this->table = $table;

        $this->deltasTable = $deltasTable;
    }

    protected function getReplayWhereClause(array $values)
    {
        $cols = $this->table->getPrimaryKey();

        $newtable = $this->getTableName();

        $conditions = array();
        $bind = array();

        foreach ($cols as $column) {
            $conditions[] = "$newtable.$column = ?";
            $bind[] = $values[$column];
        }

        return array(implode(' AND ', $conditions), $bind);
    }

    protected function getNonPrimaryKeyCols()
    {
        return array_diff($this->getTableColumns(), $this->table->getPrimaryKey());
    }

    protected function getDeltaTableName()
    {
        return $this->deltasTable->getName();
    }

    protected function getTableName()
    {
        return $this->table->getName();
    }

    protected function getTableColumns()
    {
        return $this->table->getColumns();
    }
}