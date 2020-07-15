<?php
declare(strict_types=1);

namespace Cadfael\Engine\Tests;

use Cadfael\Engine\Check;
use Cadfael\Engine\Check\MySQL\Table\EmptyTable;
use Cadfael\Engine\Entity;
use Cadfael\Engine\Entity\MySQL\Table;
use Cadfael\Engine\Report;
use PHPUnit\Framework\TestCase;

class ReportTest extends TestCase
{
    protected Entity $entity;
    protected Check $check;
    protected Report $report;

    /**
     * @throws \Cadfael\Engine\Exception\InvalidStatus
     */
    public function setUp(): void
    {
        $base = [
            "TABLE_CATALOG"     => "MOCK_CATALOG",
            "TABLE_SCHEMA"      => "MOCK_SCHEMA",
            "TABLE_NAME"        => "MOCK_TABLE",
            "TABLE_TYPE"        => "BASE TABLE",
            "ENGINE"            => "InnoDB",
            "VERSION"           => "10",
            "ROW_FORMAT"        => "Fixed",
            "TABLE_ROWS"        => 200,
            "AVG_ROW_LENGTH"    => 384,
            "DATA_LENGTH"       => 2311,
            "MAX_DATA_LENGTH"   => 16434816,
            "INDEX_LENGTH"      => 0,
            "DATA_FREE"         => 0,
            "AUTO_INCREMENT"    => null,
            "CREATE_TIME"       => "2020-05-30 11:29:56",
            "UPDATE_TIME"       => null,
            "CHECK_TIME"        => null,
            "TABLE_COLLATION"   => "utf8_general_ci",
            "CHECKSUM"          => null,
            "CREATE_OPTIONS"    => "",
            "TABLE_COMMENT"     => "",
        ];

        $this->entity = Table::createFromInformationSchema($base);
        $this->check = new EmptyTable();
        $this->report = new Report(
            $this->check,
            $this->entity,
            Report::STATUS_OK,
            [ "Message 1", "Message 2" ],
            [ "data1" => "a", "data2" => [ 1, 2] ]
        );
    }

    public function testGetStatus()
    {
        $this->assertEquals(Report::STATUS_OK, $this->report->getStatus());
    }

    public function testGetStatusLabel()
    {
        $this->assertEquals("Ok", $this->report->getStatusLabel());
    }

    public function testIsValidStatus()
    {
        $this->assertTrue(Report::isValidStatus(Report::STATUS_OK));
        $this->assertTrue(Report::isValidStatus(Report::STATUS_INFO));
        $this->assertTrue(Report::isValidStatus(Report::STATUS_CONCERN));
        $this->assertTrue(Report::isValidStatus(Report::STATUS_WARNING));
        $this->assertTrue(Report::isValidStatus(Report::STATUS_CRITICAL));
        $this->assertFalse(Report::isValidStatus(100));
    }

    public function testGetMessages()
    {
        $this->assertEquals([ "Message 1", "Message 2" ], $this->report->getMessages());
    }

    public function testGetData()
    {
        $this->assertEquals([ "data1" => "a", "data2" => [ 1, 2] ], $this->report->getData());
    }

    public function testGetCheckLabel()
    {
        $this->assertEquals("EmptyTable", $this->report->getCheckLabel());
    }

    public function testGetEntity()
    {
        $this->assertEquals($this->entity, $this->report->getEntity());
    }

    public function testGetCheck()
    {
        $this->assertEquals($this->check, $this->report->getCheck());
    }

    public function test__construct_invalid_status()
    {
        $this->expectException("\Cadfael\Engine\Exception\InvalidStatus");
        new Report($this->check, $this->entity, 100);
    }
}
