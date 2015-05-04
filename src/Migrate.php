<?php namespace MysqlMigrate;

use MysqlMigrate\TableDelta\Collection\Trigger\DeleteTrigger;
use MysqlMigrate\TableDelta\Collection\Trigger\InsertTrigger;
use MysqlMigrate\TableDelta\Collection\Trigger\UpdateTrigger;
use MysqlMigrate\TableDelta\DeltasTable;
use MysqlMigrate\TableDelta\Replay\Replayer;

class Migrate
{
    private $dbDestination;

    private $dbSource;

    public function __construct(DbConnection $dbSource, DbConnection $dbDestination)
    {
        $this->dbSource = $dbSource;

        $this->dbDestination = $dbDestination;
    }

    public function migrate(DatabaseTable $sourceTable, DatabaseTable $destinationTable, \SplFileInfo $file, \Closure $afterTransfer = null)
    {
        $sourceTable = $this->createTableInstance($sourceTable);

        $this->createDestinationTable($sourceTable, $destinationTable);

        $destinationTableInst = $this->createTableInstance($destinationTable);

        $deltasTable = new DeltasTable($sourceTable);

        $triggers = $this->setUpTriggers($sourceTable, $deltasTable);

        $this->transferData($file, $sourceTable, $destinationTableInst);

        $afterTransfer and $afterTransfer();

        $this->dbSource->lockAndFlush(array(
            $sourceTable->getName(),
            $deltasTable->getName(),
        ));

        $diffReplayer = new Replayer($this->dbSource, $this->dbDestination, $destinationTableInst, $deltasTable);

        $diffReplayer->replayChanges();

        foreach($triggers as $trigger)
        {
            $this->dbSource->dropTrigger($trigger->getName());
        }

        $this->dbSource->drop($deltasTable->getName());
    }

    private function createDestinationTable(Table $sourceTable, DatabaseTable $destinationTable)
    {
        $create = $sourceTable->getCreate();

        $newName = $destinationTable->getFQName();

        $tableParts = explode('.', $sourceTable->getName());
        $table =  '`' . str_replace('`', '', end($tableParts)) . '`';

        $count = 1;

        $create =  str_replace($table, $newName , $create, $count);

        $this->dbDestination->query($create);
    }

    /**
     * @param DatabaseTable $table
     * @return Table
     */
    private function createTableInstance(DatabaseTable $table)
    {
        $create = $this->dbSource->showCreate($table->getFQName());

        $columns = $this->dbSource->listColumns($table->getFQName());

        $primaryKey = $this->dbSource->listPrimaryKeyColumns($table->getFQName());

        $mainTable = new Table($table, $create, $columns, $primaryKey);

        return $mainTable;
    }

    /**
     * @param TableInterface $mainTable
     * @param TableInterface $deltasTable
     * @return TableDelta\Collection\Trigger\TriggerInterface[]
     */
    private function setUpTriggers(TableInterface $mainTable, TableInterface $deltasTable)
    {
        $this->dbSource->exec($deltasTable->getCreate());

        $triggers = $this->getTriggers($mainTable, $deltasTable);

        foreach($triggers as $trigger)
        {
            $sql = $trigger->getCreateStatement();

            $this->dbSource->exec($sql);
        }

        return $triggers;
    }

    /**
     * @param TableInterface $mainTable
     * @param TableInterface $deltasTable
     * @return \MysqlMigrate\TableDelta\Collection\Trigger\TriggerInterface[]
     */
    private function getTriggers(TableInterface $mainTable, TableInterface $deltasTable)
    {
        $table = $mainTable->getTable();

        return array(
            new DeleteTrigger($mainTable, $deltasTable, $table->getDatabase() . '._delta_trigger_delete_' . $table->getName()),
            new InsertTrigger($mainTable, $deltasTable, $table->getDatabase() . '._delta_trigger_insert_' . $table->getName()),
            new UpdateTrigger($mainTable, $deltasTable, $table->getDatabase() . '._delta_trigger_update_' . $table->getName()),
        );
    }

    private function transferData(\SplFileInfo $file, Table $source, Table $destination)
    {
        $countOut = $this->dbSource->selectIntoOutfile($source->getName(), $file)->rowCount();

        $countIn = $this->dbDestination->loadDataInfile($destination->getName(), $file);

        if($countOut !== $countIn)
        {
            throw new \RuntimeException("Output row count [$countOut] is not equal to input count [$countIn]");
        }
    }
}