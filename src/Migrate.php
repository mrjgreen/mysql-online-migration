<?php namespace MysqlMigrate;

use MysqlMigrate\TableDelta\Collection\Trigger\DeleteTrigger;
use MysqlMigrate\TableDelta\Collection\Trigger\InsertTrigger;
use MysqlMigrate\TableDelta\Collection\Trigger\UpdateTrigger;
use MysqlMigrate\TableDelta\DeltasTable;
use MysqlMigrate\TableDelta\Replay\Replayer;

class TransferSet
{
    public $sourceTable;

    public $destinationTable;

    public $deltasTable;

    public $triggers;

    public function __construct(Table $sourceTable, Table $destinationTable, DeltasTable $deltasTable, array $triggers)
    {
        $this->sourceTable = $sourceTable;

        $this->destinationTable = $destinationTable;

        $this->deltasTable = $deltasTable;

        $this->triggers = $triggers;
    }
}

class Migrate
{
    private $dbDestination;

    private $dbSource;

    private $tempFileProvider;

    /**
     * @param DbConnection $dbSource
     * @param DbConnection $dbDestination
     * @param TempFileProvider $tempFileProvider
     */
    public function __construct(DbConnection $dbSource, DbConnection $dbDestination, TempFileProvider $tempFileProvider)
    {
        $this->dbSource = $dbSource;

        $this->dbDestination = $dbDestination;

        $this->tempFileProvider = $tempFileProvider;
    }

    /**
     * @param array $tables
     * @param callable $afterTransfer
     */
    public function migrate(array $tables, \Closure $afterTransfer = null)
    {
        $transferSets = array();

        foreach($tables as $table)
        {
            list($sourceTableName, $destinationTableName) = $table;

            $sourceTable = new Table($this->dbSource, $sourceTableName);

            $destinationTable = new Table($this->dbDestination, $destinationTableName);

            $destinationTable->create($sourceTable->getCreate());

            $deltasTable = new DeltasTable($sourceTable, new TableName($sourceTableName->schema, '_deltas_table_' . $sourceTableName->name));

            $triggers = $this->getTriggers($sourceTable, $deltasTable, $sourceTableName);

            $transferSets[] = new TransferSet($sourceTable, $destinationTable, $deltasTable, $triggers);
        }

        $file = $this->tempFileProvider->getTempFile('testfile');

        foreach($transferSets as $transferSet)
        {
            is_file($file) && unlink($file);

            $this->setUpTriggers($transferSet->deltasTable, $transferSet->triggers);

            $this->transferData($file, $transferSet->sourceTable, $transferSet->destinationTable);
        }

        $afterTransfer and $afterTransfer();

        $this->lockTables($transferSets);

        foreach($transferSets as $transferSet)
        {
            is_file($file) && unlink($file);

            $this->replayDeltas($file, $transferSet->sourceTable, $transferSet->destinationTable, $transferSet->deltasTable);
        }

        is_file($file) && unlink($file);

        foreach($transferSets as $transferSet)
        {
            $this->verifyTables($transferSet->sourceTable, $transferSet->destinationTable);
        }

        //$afterVerify and $afterVerify();

        $this->unlockTables();

        foreach($transferSets as $transferSet)
        {
            $this->cleanupTriggers($transferSet->triggers, $transferSet->deltasTable);
        }
    }

    private function verifyTables($sourceTable, $destinationTable)
    {
        $source = $sourceTable->getName();
        $destination = $destinationTable->getName();

        $countSource = $this->dbSource->count($source);

        $countDestination = $this->dbDestination->count($destination);

        if($countSource !== $countDestination)
        {
            throw new \Exception("Count from source $source '$countSource' does not match destination $destination '$countDestination'");
        }
    }

    /**
     */
    private function unlockTables()
    {
        $this->dbSource->unlock();
    }

    /**
     * @param array $transferSets
     */
    private function lockTables(array $transferSets)
    {
        $tables = array();

        foreach($transferSets as $set)
        {
            $tables[] = $set->deltasTable->getName();
            $tables[] = $set->sourceTable->getName();
        }

        $this->dbSource->lock($tables);
    }

    /**
     * @param array $triggers
     * @param DeltasTable $deltasTable
     */
    private function cleanupTriggers(array $triggers, DeltasTable $deltasTable)
    {
        foreach($triggers as $trigger)
        {
            $this->dbSource->dropTrigger($trigger->getName());
        }

        $this->dbSource->drop($deltasTable->getName());
    }

    /**
     * @param $file
     * @param $sourceTable
     * @param $destinationTable
     * @param $deltasTable
     */
    private function replayDeltas($file, $sourceTable, $destinationTable, $deltasTable)
    {
        $diffReplayer = new Replayer($this->dbSource, $this->dbDestination, $sourceTable, $destinationTable, $deltasTable);

        $diffReplayer->replayDeletes();
        $diffReplayer->replayInsertsAndUpdates($file);
    }

    /**
     * @param TableInterface $deltasTable
     * @param array $triggers
     * @return array
     */
    private function setUpTriggers(TableInterface $deltasTable, array $triggers)
    {
        $this->dbSource->exec($deltasTable->getCreate());

        foreach($triggers as $trigger)
        {
            $sql = $trigger->getCreateStatement();

            $this->dbSource->exec($sql);
        }

        return $triggers;
    }

    /**
     * @param TableInterface $sourceTable
     * @param TableInterface $deltasTable
     * @return array
     */
    private function getTriggers(TableInterface $sourceTable, TableInterface $deltasTable)
    {
        $schema = $sourceTable->getName()->schema;
        $suffix = $sourceTable->getName()->name;

        return array(
            new DeleteTrigger($sourceTable, $deltasTable, new TableName($schema, "_deltas_trigger_delete_{$suffix}")),
            new InsertTrigger($sourceTable, $deltasTable, new TableName($schema, "_deltas_trigger_insert_{$suffix}")),
            new UpdateTrigger($sourceTable, $deltasTable, new TableName($schema, "_deltas_trigger_update_{$suffix}")),
        );
    }

    /**
     * @param \SplFileInfo $file
     * @param Table $source
     * @param Table $destination
     */
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