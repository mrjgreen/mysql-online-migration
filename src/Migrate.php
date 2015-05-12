<?php namespace MysqlMigrate;

use MysqlMigrate\TableDelta\Collection\Trigger\DeleteTrigger;
use MysqlMigrate\TableDelta\Collection\Trigger\InsertTrigger;
use MysqlMigrate\TableDelta\Collection\Trigger\UpdateTrigger;
use MysqlMigrate\TableDelta\DeltasTable;
use MysqlMigrate\TableDelta\Replay\Replayer;
use Spork\Batch\Strategy\ChunkStrategy;
use Spork\ProcessManager;

class Migrate
{
    private $dbDestination;

    private $dbSource;

    private $tempFileProvider;

    public function __construct(DbConnection $dbSource, DbConnection $dbDestination, TempFileProvider $tempFileProvider)
    {
        $this->dbSource = $dbSource;

        $this->dbDestination = $dbDestination;

        $this->tempFileProvider = $tempFileProvider;
    }

    private function setUpMigration(TableName $sourceTableName, TableName $destinationTableName)
    {
        $sourceTable = new Table($this->dbSource, $sourceTableName);

        $destinationTable = new Table($this->dbDestination, $destinationTableName);

        $destinationTable->create($sourceTable->getCreate());

        $deltasTable = new DeltasTable($sourceTable, new TableName($sourceTableName->schema, '_deltas_table_' . $sourceTableName->name));

        return array($deltasTable, $this->setUpTriggers($sourceTable, $deltasTable, $sourceTableName));
    }

    private function tearDownMigration($triggers, $file, TableName $sourceTableName, TableName $destinationTableName, $deltasTable)
    {
        $sourceTable = new Table($this->dbSource, $sourceTableName);

        $destinationTable = new Table($this->dbDestination, $destinationTableName);

        $this->replayDeltas($file, $destinationTable, $sourceTable, $deltasTable);

        foreach($triggers as $trigger)
        {
            $this->dbSource->dropTrigger($trigger->getName());
        }

        $this->dbSource->drop($deltasTable->getName());
    }

    public function migrate(array $tables, \Closure $afterTransfer = null)
    {
        $triggers = array();
        $deltasTables = array();
        $tablesToLock = array();

        foreach($tables as $table)
        {
            $sourceTableName = $table[0];

            $destinationTableName = $table[1];

            list($deltasTables[$table[0]->getQualifiedName()], $triggers[$table[0]->getQualifiedName()]) = $this->setUpMigration($sourceTableName, $destinationTableName);

            $tablesToLock[] = $table[0]->getQualifiedName();
            $tablesToLock[] = $deltasTables[$table[0]->getQualifiedName()];
        }

        $this->fork(function($table){

            $sourceTableName = $table[0];

            $destinationTableName = $table[1];

            $sourceTable = new Table($this->dbSource, $sourceTableName);

            $destinationTable = new Table($this->dbDestination, $destinationTableName);

            $file = $this->tempFileProvider->getTempFile($sourceTableName->schema . '_' . $sourceTableName->name);

            if(is_file($file))
            {
                unlink($file);
            }

            $this->transferData($file, $sourceTable, $destinationTable);

            unlink($file);
        }, $tables);


        $afterTransfer and $afterTransfer();

        $this->dbSource->lockAndFlush($tablesToLock);

        foreach($tables as $table)
        {
            $sourceTableName = $table[0];

            $destinationTableName = $table[1];

            $this->tearDownMigration($triggers[$sourceTableName->getQualifiedName()], $file, $sourceTableName, $destinationTableName, $deltasTables[$sourceTableName->getQualifiedName()]);

            unlink($file);
        }
    }

    private function replayDeltas($file, $destinationTable, $sourceTable, $deltasTable)
    {
        $diffReplayer = new Replayer($this->dbSource, $this->dbDestination, $sourceTable, $destinationTable, $deltasTable);

        $diffReplayer->replayDeletes();
        $diffReplayer->replayInsertsAndUpdates($file);
    }

    /**
     * @param TableInterface $mainTable
     * @param TableInterface $deltasTable
     * @param TableName $sourceTable
     * @return array
     */
    private function setUpTriggers(TableInterface $mainTable, TableInterface $deltasTable, TableName $sourceTable)
    {
        $this->dbSource->exec($deltasTable->getCreate());

        $triggers = $this->getTriggers($mainTable, $deltasTable, $sourceTable);

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
     * @param TableName $sourceTable
     * @return array
     */
    private function getTriggers(TableInterface $mainTable, TableInterface $deltasTable, TableName $sourceTable)
    {
        $schema = $sourceTable->schema;
        $suffix = $sourceTable->name;

        return array(
            new DeleteTrigger($mainTable, $deltasTable, new TableName($schema, "_deltas_trigger_delete_{$suffix}")),
            new InsertTrigger($mainTable, $deltasTable, new TableName($schema, "_deltas_trigger_insert_{$suffix}")),
            new UpdateTrigger($mainTable, $deltasTable, new TableName($schema, "_deltas_trigger_update_{$suffix}")),
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

    private function fork($callback, array $tables)
    {
        $manager = new ProcessManager();

        $manager->process($tables, $callback, new ChunkStrategy(count($tables)));
    }
}