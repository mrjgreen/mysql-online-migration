<?php namespace MysqlMigrate\TableDelta\Collection\Trigger;

use MysqlMigrate\TableDelta\DeltasTable;

class InsertTrigger extends TriggerAbstract
{
    public function getCreateStatement()
    {
        $trigger = 'CREATE TRIGGER %s AFTER INSERT ON %s FOR EACH ROW INSERT INTO %s(%s, %s) VALUES (%d, %s)';

        return sprintf($trigger,
            $this->getTriggerName(),
            $this->getTableName(),
            $this->getDeltaTableName(),
            DeltasTable::TYPE_COLUMN_NAME,
            $this->getColumnsString(),
            DeltasTable::TYPE_INSERT,
            $this->getColumnsString('NEW'));
    }
}