<?php namespace MysqlMigrate;

use MysqlMigrate\TableDelta\Collection\Trigger\DeleteTrigger;
use MysqlMigrate\TableDelta\Collection\Trigger\InsertTrigger;
use MysqlMigrate\TableDelta\Collection\Trigger\UpdateTrigger;
use MysqlMigrate\TableDelta\DeltasTable;
use MysqlMigrate\TableDelta\Replay\Replayer;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventDispatcher;

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
    use LoggerAwareTrait;

    private $dbDestination;

    private $dbSource;

    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * @param DbConnection $dbSource
     * @param DbConnection $dbDestination
     * @param EventDispatcher $eventDispatcher
     */
    public function __construct(DbConnection $dbSource, DbConnection $dbDestination, EventDispatcher $eventDispatcher = null)
    {
        $this->dbSource = $dbSource;

        $this->dbDestination = $dbDestination;

        $this->eventDispatcher = $eventDispatcher;
    }

    public function cleanup(array $tables)
    {
        foreach($tables as $table)
        {
            list($sourceTableName) = $table;

            $sourceTable = new Table($this->dbSource, $sourceTableName);

            $deltasTable = new DeltasTable($sourceTable, new TableName($sourceTableName->schema, '_deltas_table_' . $sourceTableName->name));

            $triggers = $this->getTriggers($sourceTable, $deltasTable, $sourceTableName);

            $this->cleanupTriggers($triggers, $deltasTable);
        }
    }

    /**
     * @param array $tables
     * @param \SplFileInfo $tmpFile
     * @param bool $overwriteExistingTables
     * @throws \Exception
     */
    public function migrate(array $tables, \SplFileInfo $tmpFile, $overwriteExistingTables = false)
    {
        try
        {
            $this->doMigration($tables, $tmpFile, $overwriteExistingTables);

        }catch (\Exception $e)
        {
            $this->unlockTables();

            throw $e;
        }
    }

    /**
     * @param array $tables
     * @param \SplFileInfo $tmpFile
     * @param $overwriteExistingTables
     * @throws \Exception
     */
    public function doMigration(array $tables, \SplFileInfo $tmpFile, $overwriteExistingTables)
    {
        $transferSets = array();

        foreach($tables as $table)
        {
            list($sourceTableName, $destinationTableName) = $table;

            $sourceTable = new Table($this->dbSource, $sourceTableName);

            $destinationTable = new Table($this->dbDestination, $destinationTableName);

            $destinationTable->create($sourceTable->getCreate(), $overwriteExistingTables);

            $deltasTable = new DeltasTable($sourceTable, new TableName($sourceTableName->schema, '_deltas_table_' . $sourceTableName->name));

            $triggers = $this->getTriggers($sourceTable, $deltasTable, $sourceTableName);

            $transferSets[] = new TransferSet($sourceTable, $destinationTable, $deltasTable, $triggers);
        }

        foreach($transferSets as $transferSet)
        {
            is_file($tmpFile) && unlink($tmpFile);

            $this->setUpTriggers($transferSet->deltasTable, $transferSet->triggers);

            $this->transferData($tmpFile, $transferSet->sourceTable, $transferSet->destinationTable);
        }

        $this->dispatch('migrate.transfer.initial');

        $this->log("Acquiring read lock on tables");

        $this->lockTables($transferSets);

        foreach($transferSets as $transferSet)
        {
            is_file($tmpFile) && unlink($tmpFile);

            $this->replayDeltas($tmpFile, $transferSet->sourceTable, $transferSet->destinationTable, $transferSet->deltasTable);
        }

        is_file($tmpFile) && unlink($tmpFile);

        $this->dispatch('migrate.transfer.delta');

        foreach($transferSets as $transferSet)
        {
            $this->verifyTables($transferSet->sourceTable, $transferSet->destinationTable);
        }

        $this->log("Acquiring write lock on tables");

        $this->lockTables($transferSets, true);

        $this->dispatch('migrate.before-unlock');

        $this->unlockTables();

        foreach($transferSets as $transferSet)
        {
            $this->cleanupTriggers($transferSet->triggers, $transferSet->deltasTable);
        }

        $this->dispatch('migrate.complete');
    }

    /**
     * @param $eventName
     */
    private function dispatch($eventName)
    {
        if($this->eventDispatcher)
        {
            $this->eventDispatcher->dispatch($eventName);
        }
    }

    /**
     * @param $sourceTable
     * @param $destinationTable
     * @throws \Exception
     */
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

        $this->log("Verified source row count $source ($countSource) against destination $destination ($countDestination)");
    }

    /**
     *
     */
    private function unlockTables()
    {
        $this->dbSource->unlock();
    }

    /**
     * @param array $transferSets
     * @param bool $write
     */
    private function lockTables(array $transferSets, $write = false)
    {
        $tables = array();

        foreach($transferSets as $set)
        {
            $tables[] = $set->deltasTable->getName();
            $tables[] = $set->sourceTable->getName();
        }

        $this->dbSource->lock($tables, $write);
    }

    /**
     * @param array $triggers
     * @param DeltasTable $deltasTable
     */
    private function cleanupTriggers(array $triggers, DeltasTable $deltasTable)
    {
        foreach($triggers as $trigger)
        {
            $t = $trigger->getName();

            $this->log("Dropping trigger $t");

            $this->dbSource->dropTrigger($t);
        }

        $d = $deltasTable->getName();

        $this->log("Dropping deltas table $d");

        $this->dbSource->drop($d);
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

        $this->log("Replaying deletes against $destinationTable");

        $diffReplayer->replayDeletes();

        $this->log("Replaying inserts against $destinationTable");
        $diffReplayer->replayInsertsAndUpdates($file);
    }

    /**
     * @param TableInterface $deltasTable
     * @param array $triggers
     * @return array
     */
    private function setUpTriggers(TableInterface $deltasTable, array $triggers)
    {
        $this->log("Creating deltas table " . $deltasTable->getName());

        $this->dbSource->exec($deltasTable->getCreate());

        foreach($triggers as $trigger)
        {
            $this->log("Creating trigger " . $trigger->getName());

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
        $this->log("Writing data from [ ".$source->getName()." ] to [ $file ]");

        $countOut = $this->dbSource->selectIntoOutfile($source->getName(), $file)->rowCount();

        $this->log("Output row count [$countOut]");

        $countIn = $this->dbDestination->loadDataInfile($destination->getName(), $file);

        $this->log("Input row count [$countIn]");

        if($countOut !== $countIn)
        {
            $this->log("Output row count [$countOut] is not equal to input count [$countIn]", LogLevel::WARNING);
        }
    }

    private function log($message, $level = LogLevel::NOTICE)
    {
        $this->logger and $this->logger->log($level, $message);
    }
}