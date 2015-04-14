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

    protected function buildConditionArray($tableA, $tableB, array $columns)
    {
        $conditions = array();

        foreach ($columns as $column) {
            $conditions[] = "$tableA.$column = $tableB.$column";
        }

        return $conditions;
    }

    protected function getReplayJoinClause()
    {
        $cols = $this->table->getPrimaryKey();

        $newtable = $this->getTableName();

        $deltas = $this->getDeltaTableName();

        return implode(' AND ', $this->buildConditionArray($newtable, $deltas, $cols));
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