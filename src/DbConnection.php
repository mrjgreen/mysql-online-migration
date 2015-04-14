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

    private function listColumn($sql, array $bind = array())
    {
        $stmt = $this->query($sql, $bind);

        $list = array();

        while($item = $stmt->fetchColumn())
        {
            $list[] = $item;
        }

        return $list;
    }

    public function lockAndFlush($database, $table)
    {
        $db = "$database.$table";

        $this->pdo->query("LOCK TABLES $db WRITE");
        $this->pdo->query("FLUSH TABLES $db");
    }
}