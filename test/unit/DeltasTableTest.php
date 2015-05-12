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

        $deltasTable = new DeltasTable($mainTable->reveal(), new TableName('foo', '_delta_bar'));

        $this->assertEquals('`foo`.`_delta_bar`', $deltasTable->getName());
    }

    public function testItReturnsCorrectColumnsFromParentTable()
    {
        $mainTable = $this->prophesize('MysqlMigrate\TableInterface');

        $mainTable->getPrimaryKey()->willReturn(array('fizz', 'buzz'));

        $deltasTable = new DeltasTable($mainTable->reveal(), new TableName('fizz', 'buzz'));

        $this->assertEquals(array(DeltasTable::TYPE_COLUMN_NAME, 'fizz', 'buzz'), $deltasTable->getColumns());
    }

    public function testItReturnsCorrectPrimaryKey()
    {
        $mainTable = $this->prophesize('MysqlMigrate\TableInterface');

        $mainTable->getPrimaryKey()->willReturn(array('fizz', 'buzz'));

        $deltasTable = new DeltasTable($mainTable->reveal(), new TableName('fizz', 'buzz'));

        $this->assertEquals(array(DeltasTable::TYPE_COLUMN_NAME, 'fizz', 'buzz'), $deltasTable->getPrimaryKey());
    }

    public function testItReturnsCorrectCreateStatement()
    {
        $mainTable = $this->prophesize('MysqlMigrate\TableInterface');

        $mainTable->getName()->willReturn('foo.bar');

        $mainTable->getPrimaryKey()->willReturn(array('fizz', 'buzz'));

        $deltasTable = new DeltasTable($mainTable->reveal(), new TableName('foo', '_delta_bar'));

        $create =
            'CREATE TABLE IF NOT EXISTS `foo`.`_delta_bar` ' .
            '(_delta_statement_type TINYINT, PRIMARY KEY(_delta_statement_type, fizz, buzz)) '.
            'ENGINE=InnoDB AS (SELECT fizz, buzz FROM foo.bar LIMIT 0)';

        $this->assertEquals($create, $deltasTable->getCreate());
    }
}