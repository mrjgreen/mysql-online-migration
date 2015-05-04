<?php namespace MysqlMigrate;

class Table implements TableInterface
{
    private $table;

    private $create;

    private $columns;

    private $primaryKey;

    public function __construct(DatabaseTable $table, $create, array $columns, array $primaryKey)
    {
        $this->table = $table;

        $this->create = $create;

        $this->columns = $columns;

        $this->primaryKey = $primaryKey;
    }

    public function getName()
    {
        return $this->table->getFQName();
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getCreate()
    {
        return $this->create;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }
}