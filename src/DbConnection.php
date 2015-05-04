<?php namespace MysqlMigrate;

class DbConnection
{
    protected $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function exec($sql)
    {
        echo "$sql \n";
        return $this->pdo->exec($sql);
    }

    public function query($sql, array $bind = array())
    {
        echo "$sql \n";
        $stmt = $this->pdo->prepare($sql);

        $stmt->execute($bind);

        return $stmt;
    }

    public function lockAndFlush(array $tables)
    {
        $tablesLock = implode(" WRITE, ", $tables) . " WRITE";
        $tables = implode(", ", $tables);

        $this->exec("LOCK TABLES $tablesLock");
        $this->exec("FLUSH TABLES $tables");
    }

    public function selectIntoOutfile($table, \SplFileInfo $file)
    {
        return $this->query("SELECT * INTO OUTFILE '$file' FROM $table");
    }

    public function loadDataInfile($table, \SplFileInfo $file)
    {
        return $this->exec("LOAD DATA LOCAL INFILE '$file' INTO TABLE $table CHARACTER SET binary");
    }

    public function showCreate($table)
    {
        return $this->query("SHOW CREATE TABLE $table")->fetchAll()[0]['Create Table'];
    }

    private function getColumnInfo($table)
    {
        $rows = $this->query("SHOW COLUMNS FROM $table")->fetchAll();

        $columns = array();

        foreach($rows as $row)
        {
            $columns[$row['Field']] = $row;
        }

        return $columns;
    }

    public function listColumns($table)
    {
        return array_keys($this->getColumnInfo($table));
    }

    public function listPrimaryKeyColumns($table)
    {
        return array_keys(array_filter($this->getColumnInfo($table), function($row){
            return $row['Key'] == 'PRI';
        }));
    }

    public function rename($table, $tableNew)
    {
        $this->exec("RENAME TABLE $table TO $tableNew");
    }

    public function drop($table)
    {
        $this->exec("DROP TABLE $table");
    }

    public function dropTrigger($trigger)
    {
        $this->exec("DROP TRIGGER $trigger");
    }

    public static function makePdoConnection($dsn, $username, $password)
    {
        $pdo = new \PDO($dsn, $username, $password, array(
            \PDO::ATTR_CASE                 => \PDO::CASE_NATURAL,
            \PDO::ATTR_ERRMODE              => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_STRINGIFY_FETCHES    => false,
            \PDO::ATTR_EMULATE_PREPARES     => false,
            \PDO::ATTR_DEFAULT_FETCH_MODE   => \PDO::FETCH_ASSOC,
            \PDO::MYSQL_ATTR_LOCAL_INFILE    => true
        ));

        $pdo->exec("set names utf8 collate utf8_unicode_ci");

        return $pdo;
    }
}