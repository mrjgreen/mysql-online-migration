<?php namespace MysqlMigrate;

class DatabaseTable
{
    private $table;

    private $database;

    public function __construct($database, $table)
    {
        $this->table = $table;

        $this->database = $database;
    }

    public function getName()
    {
        return $this->table;
    }

    public function getDatabase()
    {
        return $this->database;
    }

    public function getFQName()
    {
        return "$this->database.$this->table";
    }
}