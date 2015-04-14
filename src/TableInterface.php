<?php namespace MysqlMigrate;

interface TableInterface
{
    public function getName();

    public function getCreate();

    public function getColumns();

    public function getPrimaryKey();
}