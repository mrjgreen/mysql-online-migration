<?php namespace MysqlMigrate;

class TableName
{
    public $schema;

    public $name;

    public function __construct($schema, $name)
    {
        $this->schema = $schema;

        $this->name = $name;
    }

    public function getQualifiedName()
    {
        return "`$this->schema`.`$this->name`";
    }

    public function __toString()
    {
        return $this->getQualifiedName();
    }
}
