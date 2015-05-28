<?php namespace MysqlMigrate\Helper;

use MysqlMigrate\DbConnection;

class TableLister
{
    private $source;

    public function __construct(DbConnection $source)
    {
        $this->source = $source;
    }

    public function getTableList($database, $tableLike = null)
    {
        $sql = "SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA = ?";

        $bind = array($database);

        if($tableLike)
        {
            $sql .= " AND TABLE_NAME LIKE ?";
            $bind[] = $tableLike;
        }

        $tables = $this->source->query($sql, $bind)->fetchAll();

        $names = array();

        foreach($tables as $table)
        {
            $names[] = $table['TABLE_NAME'];
        }

        return $names;
    }

    /**
     * @param array $tablesList
     * @param array $patterns
     * @return array
     */
    public function filterTables(array $tablesList, array $patterns)
    {
        return array_filter($tablesList, function($table) use($patterns){
            return $this->inFilterList($patterns, $table);
        });
    }

    /**
     * @param array $filter
     * @param $table
     * @return bool
     */
    private function inFilterList(array $filter, $table)
    {
        foreach($filter as $f)
        {
            if(self::is($f, $table))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a given string matches a given pattern.
     *
     * @param  string  $pattern
     * @param  string  $value
     * @return bool
     */
    private static function is($pattern, $value)
    {
        if ($pattern == $value) return true;
        $pattern = preg_quote($pattern, '#');
        // Asterisks are translated into zero-or-more regular expression wildcards
        // to make it convenient to check if the strings starts with the given
        // pattern such as "library/*", making any string check convenient.
        $pattern = str_replace('\*', '.*', $pattern).'\z';
        return (bool) preg_match('#^'.$pattern.'#', $value);
    }
}