<?php namespace MysqlMigrate;

class Migrate
{
    private $dbDestination;

    private $dbSource;

    public function __construct(DbConnection $dbSource, DbConnection $dbDestination)
    {
        $this->dbSource = $dbSource;

        $this->dbDestination = $dbDestination;
    }

    public function migrate($database, $table)
    {
        $create = $this->dbSource->showCreate("$database.$table");

        $columns = $this->dbSource->listColumns("$database.$table");

        $primaryKey = $this->dbSource->listPrimaryKeyColumns("$database.$table");

        $mainTable = new \MysqlMigrate\Table("$database.$table", $create, $columns, $primaryKey);

        $deltasTable = new \MysqlMigrate\TableDelta\DeltasTable($mainTable);

        $diffTracker = new \MysqlMigrate\DiffTracker($this->dbSource);

        $diffTracker->setUp($mainTable, $deltasTable);

        $this->dbDestination->query($mainTable->getCreate());

        $filename = '/filename.csv';

        $this->dbSource->query("SELECT * INTO OUTFILE '$filename' FROM " . $mainTable->getName());

        $this->dbDestination->query("LOAD DATA INFILE LOCAL '$filename' INTO TABLE " . $mainTable->getName());

        $diffReplayer = new DiffReplayer($mainTable, $deltasTable);

        $diffReplayer->replayChanges();
    }
}