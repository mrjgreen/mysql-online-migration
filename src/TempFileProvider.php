<?php namespace MysqlMigrate;

class TempFileProvider
{
    private $tempDirectory;

    public function __construct($tempDirectory)
    {
        $this->tempDirectory = $tempDirectory;
    }

    public function getTempFile($name)
    {
        return new \SplFileInfo($this->tempDirectory . '/' . $name . '.dat');
    }
}