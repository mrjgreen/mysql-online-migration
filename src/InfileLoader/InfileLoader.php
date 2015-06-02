<?php namespace MysqlMigrate\InfileLoader;

use MysqlMigrate\DbConnection;
use MysqlMigrate\Helper\CopierInterface;

class InfileLoader implements InfileLoaderInterface
{
    private $dbDest;

    private $scpCopier;

    public function __construct(CopierInterface $scpCopier, DbConnection $dbDest)
    {
        $this->dbDest = $dbDest;

        $this->scpCopier = $scpCopier;
    }

    public function load($table, \SplFileInfo $file)
    {
        $this->scpCopier->copy($file);

        return $this->dbDest->loadDataInfile($table, $file);
    }
}
