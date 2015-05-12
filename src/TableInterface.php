<?php namespace MysqlMigrate;

interface TableInterface
{
    /**
     * @return string
     */
    public function getName();

    /**
     * @return string
     */
    public function getCreate();

    /**
     * @return array
     */
    public function getColumns();

    /**
     * @return array
     */
    public function getPrimaryKey();
}