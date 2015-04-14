<?php namespace MysqlMigrate;

use MysqlMigrate\TableDelta\Collection\Trigger\DeleteTrigger;
use MysqlMigrate\TableDelta\Collection\Trigger\InsertTrigger;
use MysqlMigrate\TableDelta\Collection\Trigger\UpdateTrigger;
use MysqlMigrate\TableDelta\Replay\ReplayDelete;
use MysqlMigrate\TableDelta\Replay\ReplayInsert;
use MysqlMigrate\TableDelta\Replay\ReplayUpdate;

class DeltaReplayTest extends \PHPUnit_Framework_TestCase
{
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

    public function testItCreatesAnInsertReplay()
    {
        $mainTable = $this->getMainTableMock();

        $deltasTable = $this->getDeltasTableMock();

        $replay = new ReplayInsert($mainTable->reveal(), $deltasTable->reveal());

        $sql = $replay->getDiffStatement(1234);

        $expectedSql = 'INSERT INTO foo(fizz,buzz) SELECT fizz,buzz FROM bar WHERE bar._delta_id = 1234';

        $this->assertEquals($expectedSql, $sql);
    }

    public function testItCreatesADeleteReplay()
    {
        $mainTable = $this->getMainTableMock();

        $mainTable->getPrimaryKey()->willReturn(array('fizz'));

        $deltasTable = $this->getDeltasTableMock();

        $replay = new ReplayDelete($mainTable->reveal(), $deltasTable->reveal());

        $sql = $replay->getDiffStatement(1234);

        $expectedSql = 'DELETE foo FROM foo, bar WHERE bar._delta_id = 1234 AND foo.fizz = bar.fizz';

        $this->assertEquals($expectedSql, $sql);
    }

    public function testItCreatesAnUpdateReplay()
    {
        $mainTable = $this->getMainTableMock();

        $mainTable->getPrimaryKey()->willReturn(array('fizz'));

        $deltasTable = $this->getDeltasTableMock();

        $replay = new ReplayUpdate($mainTable->reveal(), $deltasTable->reveal());

        $sql = $replay->getDiffStatement(1234);

        $expectedSql = 'UPDATE foo, bar SET foo.buzz = bar.buzz WHERE bar._delta_id = 1234 AND foo.fizz = bar.fizz';

        $this->assertEquals($expectedSql, $sql);
    }
}