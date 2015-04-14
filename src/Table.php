<?php namespace MysqlMigrate;

class Table implements TableInterface
{
    private $name;

    private $create;

    private $columns;

    private $primaryKey;

    public function __construct($name, $create, array $columns, array $primaryKey)
    {
        $this->name = $name;

        $this->create = $create;

        $this->columns = $columns;

        $this->primaryKey = $primaryKey;
    }

    public function getName()
    {
        return $this->name;
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