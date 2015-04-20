<?php namespace MysqlMigrate;

use MysqlMigrate\TableDelta\DeltasTable;
use MysqlMigrate\TableDelta\Replay\Replayer;
use MysqlMigrate\TableDelta\TriggerCreator;

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

        $mainTable = new Table("$database.$table", $create, $columns, $primaryKey);

        $deltasTable = new DeltasTable($mainTable);

        $diffTracker = new TriggerCreator($this->dbSource);

        $diffTracker->setUp($mainTable, $deltasTable);

        $this->dbDestination->query($mainTable->getCreate());

        $file = new \SplFileInfo('filename.csv');

        $countOut = $this->dbSource->selectIntoOutfile($mainTable->getName(), $file)->rowCount();

        $countIn = $this->dbDestination->loadDataInfile($mainTable->getName(), $file)->rowCount();

        if($countOut !== $countIn)
        {
            throw new \RuntimeException("Output row count [$countOut] is not equal to input count [$countIn]");
        }

        $this->dbSource->lockAndFlush(array(
            $mainTable->getName(),
            $deltasTable->getName(),
        ));

        $diffReplayer = new Replayer($this->dbSource, $this->dbDestination, $mainTable, $deltasTable);

        $diffReplayer->replayChanges();

        $this->dbSource->query("DROP TABLE " . $deltasTable->getName());
    }
}