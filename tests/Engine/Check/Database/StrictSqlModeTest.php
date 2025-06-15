<?php

declare(strict_types=1);


namespace Cadfael\Tests\Engine\Check\Database;

use Cadfael\Engine\Check\Database\StrictSqlMode;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\BaseTest;

class StrictSqlModeTest extends BaseTest
{
    private array $databases;

    public function setUp(): void
    {
        $this->databases = [
            '8.0_NOT_SET'      => $this->createDatabase([ 'version' => '8.0.0', 'sql_mode' => null]),
            '8.0_STRICT_A'     => $this->createDatabase([ 'version' => '8.0.0', 'sql_mode' => 'STRICT_ALL_TABLES' ]),
            '8.0_STRICT_T'     => $this->createDatabase([ 'version' => '8.0.0', 'sql_mode' => 'STRICT_TRANS_TABLES' ]),
            '8.0_TRADITIONAL'  => $this->createDatabase([ 'version' => '8.0.0', 'sql_mode' => 'TRADITIONAL' ]),
            '8.0_NOT_STRICT'   => $this->createDatabase([ 'version' => '8.0.0', 'sql_mode' => 'ON' ]),
            '5.7_NOT_STRICT'   => $this->createDatabase([ 'version' => '5.7.0', 'sql_mode' => 'ON' ]),
            '5.7_NOT_STRICT_D' => $this->createDatabase([ 'version' => '5.7.0', 'sql_mode' => 'ERROR_FOR_DIVISION_BY_ZERO' ]),
            '5.7_TRADITIONAL'  => $this->createDatabase([ 'version' => '5.7.0', 'sql_mode' => 'TRADITIONAL' ]),
            '5.7_STRICT_A'     => $this->createDatabase([ 'version' => '5.7.0', 'sql_mode' => 'STRICT_ALL_TABLES' ]),
            '5.7_STRICT_T'     => $this->createDatabase([ 'version' => '5.7.0', 'sql_mode' => 'STRICT_TRANS_TABLES' ]),
            '5.0_TRADITIONAL'  => $this->createDatabase([ 'version' => '5.0.0', 'sql_mode' => 'TRADITIONAL' ]),
            'unknown' => $this->createDatabase([ 'version' => null ])
        ];
    }

    public function testSupports()
    {
        $check = new StrictSqlMode();

        $this->assertTrue($check->supports($this->databases['8.0_NOT_SET']), "Ensure that we care about databases from 5.7.");
        $this->assertTrue($check->supports($this->databases['8.0_STRICT_A']), "Ensure that we care about databases from 5.7.");
        $this->assertTrue($check->supports($this->databases['8.0_STRICT_T']), "Ensure that we care about databases from 5.7.");
        $this->assertTrue($check->supports($this->databases['8.0_TRADITIONAL']), "Ensure that we care about databases from 5.7.");
        $this->assertTrue($check->supports($this->databases['8.0_NOT_STRICT']), "Ensure that we care about databases from 5.7.");
        $this->assertTrue($check->supports($this->databases['5.7_NOT_STRICT']), "Ensure that we care about databases from 5.7.");
        $this->assertTrue($check->supports($this->databases['5.7_NOT_STRICT_D']), "Ensure that we care about databases from 5.7.");
        $this->assertTrue($check->supports($this->databases['5.7_TRADITIONAL']), "Ensure that we care about databases from 5.7.");
        $this->assertTrue($check->supports($this->databases['5.7_STRICT_A']), "Ensure that we care about databases from 5.7.");
        $this->assertTrue($check->supports($this->databases['5.7_STRICT_T']), "Ensure that we care about databases from 5.7.");
        $this->assertFalse($check->supports($this->databases['5.0_TRADITIONAL']), "Ensure that we care about databases from 5.7.");
        $this->assertFalse($check->supports($this->databases['unknown']), "We can't support unknown versions.");
        $this->assertFalse($check->supports($this->createTable()), "We only support database entities.");
    }

    public function testRun()
    {
        $check = new StrictSqlMode();
        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($this->databases['8.0_NOT_SET'])->getStatus(),
            "Ensure we return " . Report::STATUS_OK . " if nothing is set (default)."
        );

        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($this->databases['8.0_STRICT_A'])->getStatus(),
            "Ensure we return " . Report::STATUS_OK . " if STRICT_ALL_TABLES is set."
        );

        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($this->databases['8.0_STRICT_T'])->getStatus(),
            "Ensure we return " . Report::STATUS_OK . " if STRICT_TRANS_TABLES is set."
        );

        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($this->databases['8.0_TRADITIONAL'])->getStatus(),
            "Ensure we return " . Report::STATUS_OK . " if TRADITIONAL is set (strict included)."
        );

        $this->assertEquals(
            Report::STATUS_WARNING,
            $check->run($this->databases['8.0_NOT_STRICT'])->getStatus(),
            "Ensure we return " . Report::STATUS_WARNING . " if strict is not enabled."
        );

        $this->assertEquals(
            Report::STATUS_WARNING,
            $check->run($this->databases['5.7_NOT_STRICT'])->getStatus(),
            "Ensure we return " . Report::STATUS_WARNING . " if strict is not enabled."
        );

        $this->assertEquals(
            Report::STATUS_WARNING,
            $check->run($this->databases['5.7_NOT_STRICT_D'])->getStatus(),
            "Ensure we return " . Report::STATUS_WARNING . " if strict is not enabled."
        );

        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($this->databases['5.7_TRADITIONAL'])->getStatus(),
            "Ensure we return " . Report::STATUS_OK . " if strict is not enabled."
        );

        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($this->databases['5.7_STRICT_A'])->getStatus(),
            "Ensure we return " . Report::STATUS_OK . " if strict is not enabled."
        );

        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($this->databases['5.7_STRICT_T'])->getStatus(),
            "Ensure we return " . Report::STATUS_OK . " if strict is not enabled."
        );
    }

}
