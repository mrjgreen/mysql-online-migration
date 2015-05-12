<?php namespace MysqlMigrate;

use MysqlMigrate\TableDelta\Collection\Trigger\DeleteTrigger;
use MysqlMigrate\TableDelta\Collection\Trigger\InsertTrigger;
use MysqlMigrate\TableDelta\Collection\Trigger\UpdateTrigger;
use MysqlMigrate\TableDelta\Replay\ReplayDelete;
use MysqlMigrate\TableDelta\Replay\ReplayInsert;
use MysqlMigrate\TableDelta\Replay\ReplayReplace;
use MysqlMigrate\TableDelta\Replay\ReplayUpdate;

class DeltaReplayTest extends \PHPUnit_Framework_TestCase
{
    private function getMainTableMock()
    {
        $mainTable = $this->prophesize('MysqlMigrate\TableInterface');

        $mainTable->getName()->willReturn('foo');

        return $mainTable;
    }

    private function getDeltasTableMock()
    {
        $deltaTable = $this->prophesize('MysqlMigrate\TableInterface');

        $deltaTable->getName()->willReturn('bar');

        return $deltaTable;
    }

    public function testItCreatesADeleteReplay()
    {
        $mainTable = $this->getMainTableMock();

        $mainTable->getPrimaryKey()->willReturn(array('fizz'));

        $deltasTable = $this->getDeltasTableMock();

        $replay = new ReplayDelete($mainTable->reveal(), $deltasTable->reveal());

        list($sql, $bind) = $replay->getDiffStatement(array('fizz' => '123', 'buzz' => 'foobar', 'baz' => 'boo'));

        $expectedSql = 'DELETE FROM foo WHERE foo.fizz = ?';

        $expectedBind = array(
            123
        );

        $this->assertEquals($expectedSql, $sql);

        $this->assertEquals($expectedBind, $bind);
    }
}