<?php namespace MysqlMigrate\InfileLoader;

interface InfileLoaderInterface
{
    public function load($table, \SplFileInfo $file);
}
