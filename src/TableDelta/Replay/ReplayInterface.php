<?php namespace MysqlMigrate\TableDelta\Replay;

interface ReplayInterface
{
    public function getDiffStatement(array $rowId);
}