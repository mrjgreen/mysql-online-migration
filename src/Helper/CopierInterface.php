<?php namespace MysqlMigrate\Helper;

interface CopierInterface
{
    public function copy(\SplFileInfo $file);
}