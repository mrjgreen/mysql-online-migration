<?php namespace MysqlMigrate;

use MysqlMigrate\TableDelta\Collection\TriggerCreator;

class TriggerCreatorTest extends \PHPUnit_Framework_TestCase
{
    public function testItDoesSomething()
    {
        $dbMock = $this->getMockBuilder('MysqlMigrate\DbConnection')->disableOriginalConstructor()->getMock();
        $creator = new TriggerCreator($dbMock);
    }
}