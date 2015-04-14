<?php namespace MysqlMigrate\TableDelta\Collection\Trigger;

use MysqlMigrate\TableDelta\DeltasTable;

class DeleteTrigger extends TriggerAbstract
{
    public function getCreateStatement()
    {
        $trigger = 'CREATE TRIGGER %s AFTER DELETE ON %s FOR EACH ROW INSERT INTO %s(%s, %s) VALUES (%d, %s)';

        return sprintf($trigger,
            $this->getTriggerName(),
            $this->getTableName(),
            $this->getDeltaTableName(),
            DeltasTable::TYPE_COLUMN_NAME,
            $this->getColumnsString(),
            DeltasTable::TYPE_DELETE,
            $this->getColumnsString('OLD'));
    }
}
