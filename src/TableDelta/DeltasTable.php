<?php namespace MysqlMigrate\TableDelta;

use MysqlMigrate\DatabaseTable;
use MysqlMigrate\TableInterface;

class DeltasTable implements TableInterface
{
    const TYPE_INSERT = 1;

    const TYPE_DELETE = 2;

    const TYPE_UPDATE = 3;

    const TYPE_REPLACE = 4;

    const TYPE_COLUMN_NAME = '_delta_statement_type';

    const ID_COLUMN_NAME = '_delta_id';

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
        $create = 'CREATE TABLE IF NOT EXISTS %s (%s INT UNSIGNED AUTO_INCREMENT, %s TINYINT, PRIMARY KEY(%s)) ENGINE=%s AS (SELECT %s FROM %s LIMIT 0)';

        return sprintf($create,
            $this->getName(),
            self::ID_COLUMN_NAME,
            self::TYPE_COLUMN_NAME,
            self::ID_COLUMN_NAME,
            $this->engine,
            implode(', ', $this->mainTable->getColumns()),
            $this->mainTable->getName());
    }

    public function getColumns()
    {
        return array_merge(array(self::ID_COLUMN_NAME, self::TYPE_COLUMN_NAME), $this->mainTable->getColumns());
    }

    public function getPrimaryKey()
    {
        return array(self::ID_COLUMN_NAME);
    }
}