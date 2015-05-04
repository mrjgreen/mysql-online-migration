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
    private $deltaTypeReplace = 4; //DeltasTable::TYPE_UPDATE;

    private function getMainTableMock()
    {
        $mainTable = $this->prophesize('MysqlMigrate\TableInterface');

        $mainTable->getName()->willReturn('foo');
        $mainTable->getColumns()->willReturn(array('fizz', 'buzz'));

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

        $expectedSql = "CREATE TRIGGER biz AFTER INSERT ON foo FOR EACH ROW INSERT INTO bar($this->deltaTypeCol, fizz, buzz) VALUES ($this->deltaTypeInsert, NEW.fizz, NEW.buzz)";

        $this->assertEquals($expectedSql, $sql);
    }

    public function testItCreatesADeleteTrigger()
    {
        $mainTable = $this->getMainTableMock();

        $deltaTable = $this->getDeltasTableMock();

        $insertTrigger = new DeleteTrigger($mainTable->reveal(), $deltaTable->reveal(), 'biz');

        $sql = $insertTrigger->getCreateStatement();

        $expectedSql = "CREATE TRIGGER biz AFTER DELETE ON foo FOR EACH ROW INSERT INTO bar($this->deltaTypeCol, fizz, buzz) VALUES ($this->deltaTypeDelete, OLD.fizz, OLD.buzz)";

        $this->assertEquals($expectedSql, $sql);
    }

    public function testItCreatesAnUpdateTrigger()
    {
        $mainTable = $this->getMainTableMock();

        $mainTable->getPrimaryKey()->willReturn(array('fizz'));

        $deltaTable = $this->getDeltasTableMock();

        $insertTrigger = new UpdateTrigger($mainTable->reveal(), $deltaTable->reveal(), 'biz');

        $sql = $insertTrigger->getCreateStatement();

        $expectedSql =
            "CREATE TRIGGER biz AFTER UPDATE ON foo FOR EACH ROW " .
            "IF (OLD.fizz = NEW.fizz) THEN INSERT INTO bar($this->deltaTypeCol, fizz, buzz) VALUES($this->deltaTypeUpdate, NEW.fizz, NEW.buzz); " .
            "ELSE INSERT INTO bar($this->deltaTypeCol, fizz, buzz) VALUES($this->deltaTypeReplace, NEW.fizz, NEW.buzz); END IF";

        $this->assertEquals($expectedSql, $sql);
    }
}