<?php namespace MysqlMigrate\TableDelta\Collection\Trigger;

use MysqlMigrate\TableInterface;

abstract class TriggerAbstract implements TriggerInterface
{
    private $table;

    private $deltasTable;

    private $name;

    public function __construct(TableInterface $table, TableInterface $deltasTable, $name)
    {
        $this->table = $table;

        $this->deltasTable = $deltasTable;

        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    protected function getPrimaryKeyString($prefix = null)
    {
        return implode(', ', array_map(function($column) use($prefix){
            return $prefix ? "$prefix.$column" : $column;
        }, $this->table->getPrimaryKey()));
    }

    protected function getPrimaryKey()
    {
        return $this->table->getPrimaryKey();
    }

    protected function getTriggerName()
    {
        return $this->name;
    }

    protected function getDeltaTableName()
    {
        return $this->deltasTable->getName();
    }

    protected function getTableName()
    {
        return $this->table->getName();
    }
}