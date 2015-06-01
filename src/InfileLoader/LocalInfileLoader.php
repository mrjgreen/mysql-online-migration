<?php namespace MysqlMigrate\InfileLoader;

use MysqlMigrate\DbConnection;

class LocalInfileLoader implements InfileLoaderInterface
{
    private $dbSource;

    public function __construct(DbConnection $dbSource)
    {
        $this->dbSource = $dbSource;
    }

    public function load($table, \SplFileInfo $file)
    {
        $this->dbSource->loadDataLocalInfile($table, $file);
    }
}
