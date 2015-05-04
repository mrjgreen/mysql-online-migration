<?php namespace MysqlMigrate\TableDelta\Collection\Trigger;

interface TriggerInterface
{
    public function getCreateStatement();

    public function getName();
}