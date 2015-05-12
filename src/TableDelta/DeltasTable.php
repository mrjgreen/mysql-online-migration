<?php namespace MysqlMigrate\TableDelta;

use MysqlMigrate\DatabaseTable;
use MysqlMigrate\TableInterface;

class DeltasTable implements TableInterface
{
    const TYPE_INSERT = 1;

    const TYPE_DELETE = 2;

    const TYPE_UPDATE = 3;

    const TYPE_COLUMN_NAME = '_delta_statement_type';

    const DEFAULT_ENGINE = 'InnoDB';

    private $mainTable;

    private $engine;

    public function __construct(TableInterface $mainTable, $engine = self::DEFAULT_ENGINE)
    {
        $this->mainTable = $mainTable;

        $this->engine = $engine;
    }

    public function getName()
    {
        return $this->getTable()->getFQName();
    }

    public function getTable()
    {
        $table = $this->mainTable->getTable();

        return new DatabaseTable($table->getDatabase(), '_delta_' . $table->getName());
    }

    public function getCreate()
    {
        $create = 'CREATE TABLE IF NOT EXISTS %s (%s TINYINT, PRIMARY KEY(%s)) ENGINE=%s AS (SELECT %s FROM %s LIMIT 0)';

        return sprintf($create,
            $this->getName(),
            self::TYPE_COLUMN_NAME,
            implode(', ', $this->getPrimaryKey()),
            $this->engine,
            implode(', ', $this->mainTable->getPrimaryKey()),
            $this->mainTable->getName());
    }

    public function getColumns()
    {
        return array_merge(array(self::TYPE_COLUMN_NAME), $this->mainTable->getPrimaryKey());
    }

    public function getPrimaryKey()
    {
        return $this->getColumns();
    }
}