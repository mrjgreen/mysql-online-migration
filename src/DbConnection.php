<?php namespace MysqlMigrate;

class DbConnection
{
    protected $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function query($sql, array $bind = array())
    {
        $stmt = $this->pdo->prepare($sql);

        $stmt->execute($bind);

        $stmt->setFetchMode(\PDO::FETCH_ASSOC);

        return $stmt;
    }

    public function lockAndFlush(array $tables)
    {
        $tablesLock = implode("WRITE, ", $tables) . " WRITE";
        $tables = implode(", ", $tables);

        $this->pdo->query("LOCK TABLES $tablesLock");
        $this->pdo->query("FLUSH TABLES $tables");
    }

    public function selectIntoOutfile($table, \SplFileInfo $file)
    {
        return $this->query("SELECT * INTO OUTFILE '$file' FROM $table");
    }

    public function loadDataInfile($table, \SplFileInfo $file)
    {
        return $this->query("LOAD DATA INFILE LOCAL '$file' INTO TABLE $table");
    }
}