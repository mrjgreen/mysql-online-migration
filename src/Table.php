<?php namespace MysqlMigrate;

class Table implements TableInterface
{
    private $table;

    private $connection;

    public function __construct(DbConnection $connection, TableName $table)
    {
        $this->connection = $connection;

        $this->table = $table;
    }

    public function getName()
    {
        return $this->table;
    }

    public function create($createStatement, $ifNotExists = false)
    {
        $ifNotExists ?
            $this->connection->createTableIfNotExists($this->table->getQualifiedName(), $createStatement) :
            $this->connection->createTable($this->table->getQualifiedName(), $createStatement);
    }

    public function getCreate()
    {
        $create = $this->connection->showCreate($this->getName());

        $replace = "CREATE TABLE `" . $this->table->name . "`";

        $count = 1;

        return str_replace($replace, '', $create, $count);
    }

    public function getColumns()
    {
        return $this->connection->listColumns($this->getName());
    }

    public function getPrimaryKey()
    {
        return $this->connection->listPrimaryKeyColumns($this->getName());
    }

    public function __toString()
    {
        return $this->getName()->getQualifiedName();
    }
}