<?php namespace MysqlMigrate\TableDelta\Replay;

class ReplayDelete extends ReplayAbstract
{
    public function getDiffStatement(array $row)
    {
        $newtable = $this->getTableName();

        list($where, $bind) = $this->getReplayWhereClause($row);

        return array(sprintf("DELETE FROM %s WHERE %s", $newtable, $where), $bind);
    }
}