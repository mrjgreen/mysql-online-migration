<?php namespace MysqlMigrate\TableDelta\Collection\Trigger;

use MysqlMigrate\TableDelta\DeltasTable;

class DeleteTrigger extends TriggerAbstract
{
    public function getCreateStatement()
    {
        $trigger = 'CREATE TRIGGER %s AFTER DELETE ON %s FOR EACH ROW REPLACE INTO %s(%s, %s) VALUES (%d, %s)';

        return sprintf($trigger,
            $this->getTriggerName(),
            $this->getTableName(),
            $this->getDeltaTableName(),
            DeltasTable::TYPE_COLUMN_NAME,
            $this->getPrimaryKeyString(),
            DeltasTable::TYPE_DELETE,
            $this->getPrimaryKeyString('OLD'));
    }
}
