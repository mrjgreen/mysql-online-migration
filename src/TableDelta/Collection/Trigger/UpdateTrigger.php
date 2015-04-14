<?php namespace MysqlMigrate\TableDelta\Collection\Trigger;

use MysqlMigrate\TableDelta\DeltasTable;

class UpdateTrigger extends TriggerAbstract
{
    public function getCreateStatement()
    {
        $trigger = 'CREATE TRIGGER %s AFTER UPDATE ON %s FOR EACH ROW ' .
            'IF (%s) THEN ' .
            'INSERT INTO %s(%s, %s) VALUES(%d, %s); ' .
            'ELSE ' .
            'INSERT INTO %s(%s, %s) VALUES(%d, %s); ' .
            'END IF';

        $deltaTable = $this->getDeltaTableName();

        $columns = $this->getColumnsString();

        $columnsNew = $this->getColumnsString('NEW');

        return sprintf($trigger,
            $this->getTriggerName(),
            $this->getTableName(),
            $this->buildPrimaryKeyCheck(),
            $deltaTable,
            DeltasTable::TYPE_COLUMN_NAME,
            $columns,
            DeltasTable::TYPE_UPDATE,
            $columnsNew,
            $deltaTable,
            DeltasTable::TYPE_COLUMN_NAME,
            $columns,
            DeltasTable::TYPE_REPLACE,
            $columnsNew);
    }

    private function buildPrimaryKeyCheck()
    {
        $parts = array();

        foreach($this->getPrimaryKey() as $col)
        {
            $parts[] = "OLD.$col = NEW.$col";
        }

        return implode(' AND ', $parts);
    }
}