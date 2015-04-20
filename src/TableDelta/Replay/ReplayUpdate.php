<?php namespace MysqlMigrate\TableDelta\Replay;

class ReplayUpdate extends ReplayAbstract
{
    public function getDiffStatement(array $row)
    {
        $newtable = $this->getTableName();

        list($where, $whereBind) = $this->getReplayWhereClause($row);

        $nonPrimaryKeyCols = $this->getNonPrimaryKeyCols();

        $bind = array();
        $set = array();

        foreach($nonPrimaryKeyCols as $col)
        {
            $set[] = "$col = ?";
            $bind[] = $row[$col];
        }

        foreach($whereBind as $b)
        {
            $bind[] = $b;
        }

        return array(sprintf("UPDATE %s SET %s WHERE %s", $newtable, implode(', ', $set), $where), $bind);
    }
}