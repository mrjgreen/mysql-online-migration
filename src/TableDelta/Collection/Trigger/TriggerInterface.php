<?php namespace MysqlMigrate\TableDelta\Collection\Trigger;

interface TriggerInterface
{
    public function getCreateStatement();
}