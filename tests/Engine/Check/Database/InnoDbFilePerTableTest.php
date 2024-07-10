<?php

declare(strict_types=1);


namespace Cadfael\Tests\Engine\Check\Database;

use Cadfael\Engine\Check\Database\InnoDbFilePerTable;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\BaseTest;

class InnoDbFilePerTableTest extends BaseTest
{
    private array $databases;

    public function setUp(): void
    {
        $this->databases = [
            '8.0_NOT_SET' => $this->createDatabase([ 'version' => '8.0.13', 'innodb_file_per_table' => null]),
            '8.0_OFF' => $this->createDatabase([ 'version' => '8.0.13', 'innodb_file_per_table' => 'OFF' ]),
            '8.0_ON' => $this->createDatabase([ 'version' => '8.0.13', 'innodb_file_per_table' => 'ON' ])
        ];
    }

    public function testSupports()
    {
        $check = new InnoDbFilePerTable();

        $this->assertTrue($check->supports($this->databases['8.0_NOT_SET']), "Ensure that we care about all databases.");
        $this->assertTrue($check->supports($this->databases['8.0_OFF']), "Ensure that we care about all databases.");
        $this->assertTrue($check->supports($this->databases['8.0_ON']), "Ensure that we care about all databases.");
        $this->assertFalse($check->supports($this->createTable()), "We only support database entities.");
    }

    public function testRun()
    {
        $check = new InnoDbFilePerTable();

        $this->assertEquals(
            Report::STATUS_CONCERN,
            $check->run($this->databases['8.0_NOT_SET'])->getStatus(),
            "Ensure we return " . Report::STATUS_WARNING . " for all MySQL versions with innodb_file_per_table not set."
        );

        $this->assertEquals(
            Report::STATUS_CONCERN,
            $check->run($this->databases['8.0_OFF'])->getStatus(),
            "Ensure we return " . Report::STATUS_WARNING . " for MySQL versions with innodb_file_per_table set to OFF."
        );

        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($this->databases['8.0_ON'])->getStatus(),
            "We are happy if it is enabled."
        );
    }
}
