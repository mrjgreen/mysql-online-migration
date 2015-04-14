<?php namespace MysqlMigrate;

use MysqlMigrate\TableDelta\Collection\Trigger\DeleteTrigger;
use MysqlMigrate\TableDelta\Collection\Trigger\InsertTrigger;
use MysqlMigrate\TableDelta\Collection\Trigger\UpdateTrigger;
use MysqlMigrate\TableDelta\DeltasTable;

class DeltasTableTest extends \PHPUnit_Framework_TestCase
{
    public function testItReturnsCorrectName()
    {
        $mainTable = $this->prophesize('MysqlMigrate\TableInterface');

        $mainTable->getName()->willReturn('foo');

        $deltasTable = new DeltasTable($mainTable->reveal());

        $this->assertEquals('_delta_foo', $deltasTable->getName());
    }

    public function testItReturnsCorrectColumnsFromParentTable()
    {
        $mainTable = $this->prophesize('MysqlMigrate\TableInterface');

        $mainTable->getColumns()->willReturn(array('fizz', 'buzz'));

        $deltasTable = new DeltasTable($mainTable->reveal());

        $this->assertEquals(array(DeltasTable::ID_COLUMN_NAME, DeltasTable::TYPE_COLUMN_NAME, 'fizz', 'buzz'), $deltasTable->getColumns());
    }

    public function testItReturnsCorrectPrimaryKey()
    {
        $mainTable = $this->prophesize('MysqlMigrate\TableInterface');

        $deltasTable = new DeltasTable($mainTable->reveal());

        $this->assertEquals(array(DeltasTable::ID_COLUMN_NAME), $deltasTable->getPrimaryKey());
    }

    public function testItReturnsCorrectCreateStatement()
    {
        $mainTable = $this->prophesize('MysqlMigrate\TableInterface');

        $mainTable->getName()->willReturn('foo');
        $mainTable->getColumns()->willReturn(array('fizz', 'buzz'));

        $deltasTable = new DeltasTable($mainTable->reveal());

        $create =
            'CREATE TABLE IF NOT EXISTS _delta_foo ' .
            '(_delta_id INT UNSIGNED AUTO_INCREMENT, _delta_statement_type TINYINT, PRIMARY KEY(_delta_id)) '.
            'ENGINE=InnoDB AS (SELECT fizz, buzz FROM foo LIMIT 0)';

        $this->assertEquals($create, $deltasTable->getCreate());
    }
}