<?php namespace MysqlMigrate\TableDelta\Collection;

use MysqlMigrate\DbConnection;
use MysqlMigrate\TableDelta\Collection\Trigger\DeleteTrigger;
use MysqlMigrate\TableDelta\Collection\Trigger\InsertTrigger;
use MysqlMigrate\TableDelta\Collection\Trigger\UpdateTrigger;
use MysqlMigrate\TableInterface;

class TriggerCreator
{
    private $db;

    public function __construct(DbConnection $db)
    {
        $this->db = $db;
    }

    public function setUp(TableInterface $mainTable, TableInterface $deltasTable)
    {
        foreach($this->getTriggers($mainTable, $deltasTable) as $trigger)
        {
            $sql = $trigger->getCreateStatement();

            $this->db->query($sql);
        }
    }

    private function getTriggers(TableInterface $mainTable, TableInterface $deltasTable)
    {
        return array(
            new DeleteTrigger($mainTable, $deltasTable, '_delta_trigger_delete_' . $mainTable->getName()),
            new InsertTrigger($mainTable, $deltasTable, '_delta_trigger_insert_' . $mainTable->getName()),
            new UpdateTrigger($mainTable, $deltasTable, '_delta_trigger_update_' . $mainTable->getName()),
        );
    }
}
