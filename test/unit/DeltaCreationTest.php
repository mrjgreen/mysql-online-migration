<?php namespace MysqlMigrate;

use MysqlMigrate\TableDelta\Collection\Trigger\DeleteTrigger;
use MysqlMigrate\TableDelta\Collection\Trigger\InsertTrigger;
use MysqlMigrate\TableDelta\Collection\Trigger\UpdateTrigger;

class DeltaCreationTest extends \PHPUnit_Framework_TestCase
{
    private $deltaTypeCol = '_delta_statement_type'; //DeltasTable::TYPE_COLUMN_NAME
    private $deltaTypeInsert = 1; //DeltasTable::TYPE_INSERT;
    private $deltaTypeDelete = 2; //DeltasTable::TYPE_DELETE;
    private $deltaTypeUpdate = 3; //DeltasTable::TYPE_UPDATE;

    private function getMainTableMock()
    {
        $mainTable = $this->prophesize('MysqlMigrate\TableInterface');

        $mainTable->getName()->willReturn('foo');
        $mainTable->getPrimaryKey()->willReturn(array('fizz'));

        return $mainTable;
    }

    private function getDeltasTableMock()
    {
        $deltaTable = $this->prophesize('MysqlMigrate\TableInterface');

        $deltaTable->getName()->willReturn('bar');

        return $deltaTable;
    }

    public function testItCreatesAnInsertTrigger()
    {
        $mainTable = $this->getMainTableMock();

        $deltaTable = $this->getDeltasTableMock();

        $insertTrigger = new InsertTrigger($mainTable->reveal(), $deltaTable->reveal(), 'biz');

        $sql = $insertTrigger->getCreateStatement();

        $expectedSql = "CREATE TRIGGER biz AFTER INSERT ON foo FOR EACH ROW REPLACE INTO bar($this->deltaTypeCol, fizz) VALUES ($this->deltaTypeInsert, NEW.fizz)";

        $this->assertEquals($expectedSql, $sql);
    }

    public function testItCreatesADeleteTrigger()
    {
        $mainTable = $this->getMainTableMock();

        $deltaTable = $this->getDeltasTableMock();

        $insertTrigger = new DeleteTrigger($mainTable->reveal(), $deltaTable->reveal(), 'biz');

        $sql = $insertTrigger->getCreateStatement();

        $expectedSql = "CREATE TRIGGER biz AFTER DELETE ON foo FOR EACH ROW REPLACE INTO bar($this->deltaTypeCol, fizz) VALUES ($this->deltaTypeDelete, OLD.fizz)";

        $this->assertEquals($expectedSql, $sql);
    }

    public function testItCreatesAnUpdateTrigger()
    {
        $mainTable = $this->getMainTableMock();

        $deltaTable = $this->getDeltasTableMock();

        $insertTrigger = new UpdateTrigger($mainTable->reveal(), $deltaTable->reveal(), 'biz');

        $sql = $insertTrigger->getCreateStatement();

        $expectedSql = "CREATE TRIGGER biz AFTER UPDATE ON foo FOR EACH ROW REPLACE INTO bar($this->deltaTypeCol, fizz) VALUES ($this->deltaTypeUpdate, OLD.fizz)";

        $this->assertEquals($expectedSql, $sql);
    }
}