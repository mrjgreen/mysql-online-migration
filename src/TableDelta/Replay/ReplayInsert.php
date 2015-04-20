<?php namespace MysqlMigrate\TableDelta\Replay;

class ReplayInsert extends ReplayAbstract
{
    protected $keyword = 'INSERT';

    public function getDiffStatement(array $row)
    {
        $newtable = $this->getTableName();

        $values = array_intersect_key($row, array_flip($this->getTableColumns()));

        $cols = implode(',', $this->getTableColumns());

        $qs = str_repeat('?,', count($values));

        return array(sprintf("$this->keyword INTO %s(%s) VALUES(%s)", $newtable, $cols, rtrim($qs, ',')), array_values($values));
    }
}
