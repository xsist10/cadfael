<?php
declare(strict_types=1);

namespace Cadfael\Tests\Engine\Entity;

use Cadfael\Engine\Entity\Database;
use Cadfael\Engine\Entity\Schema;
use Cadfael\Tests\Engine\BaseTest;
use Doctrine\DBAL\DriverManager;

class DatabaseTest extends BaseTest
{
    protected Database $database;

    const VERSION = '5.7';
    const VARIABLES = [
        'version' => self::VERSION
    ];

    protected function setUp(): void
    {
        $connectionParams = array(
            'dbname'   => "test",
            'user'     => "root",
            'password' => "toor",
            'host'     => "localhost",
            'port'     => '3306',
            'driver'   => 'pdo_mysql',
        );
        $connection = DriverManager::getConnection($connectionParams);

        $this->database = new Database($connection);
        $this->database->setVariables(self::VARIABLES);

        $this->database->addAccount($this->createAccount('root', 'localhost'));
        $this->database->addAccount($this->createAccount('bob', 'localhost'));
        $this->database->addAccount($this->createAccount('alice', '%'));
    }

    public function test__getName()
    {
        $this->assertEquals("localhost:3306", $this->database->getName(), "Verify that the getName() returns the correct name.");
    }

    public function test__toString()
    {
        $this->assertEquals("localhost:3306", (string)$this->database, "Verify that the toString returns the correct name.");
    }

    public function test__getAccount()
    {
        $this->assertEquals(
            $this->createAccount('bob', 'localhost'),
            $this->database->getAccount('bob', 'localhost'),
            "Verify that we get the expected Account."
        );
        $this->assertNull(
            $this->database->getAccount('bob', '%'),
            "Our fuzzy domain should not match for Bob."
        );
        $this->assertEquals(
            $this->createAccount('alice', '%'),
            $this->database->getAccount('alice', 'localhost'),
            "Our fuzzy domain should match for Alice."
        );
    }

    public function test__hasPerformanceSchema()
    {
        $this->database->setVariables([ 'performance_schema' => 'ON' ]);
        $this->assertTrue(
            $this->database->hasPerformanceSchema(),
            "Ensure we correctly detect that performance schema is on with a normal flag"
        );

        $this->database->setVariables([ 'performance_schema' => 'OFF' ]);
        $this->assertFalse(
            $this->database->hasPerformanceSchema(),
            "Ensure we correctly detect that performance schema is off with a normal flag"
        );

        $this->database->setVariables([ 'performance_schema_something' => '1' ]);
        $this->assertTrue(
            $this->database->hasPerformanceSchema(),
            "Ensure we correctly detect that performance schema is on with namespace variable."
        );

        $this->database->setVariables([]);
        $this->assertFalse(
            $this->database->hasPerformanceSchema(),
            "Ensure we correctly detect that performance schema is off due to lack of namespace variable."
        );
    }

    public function test__setSchema()
    {
        $schema = new Schema('test');
        $this->database->setSchemas($schema);

        $this->assertEquals($this->database, $schema->getDatabase(), "Ensure that the assignment of a schema ensures injecting the database into the schema.");
    }

    public function test__isVirtual()
    {
        $this->assertFalse($this->database->isVirtual(), "Verify that the schema is correctly identified as not virtual.");;
    }

    public function test__getVersion()
    {
        $this->assertEquals($this->database->getVersion(), self::VERSION, "Ensure the correct version is returned.");
    }

    public function test__getVariables()
    {
        $this->assertEquals($this->database->getVariables(), self::VARIABLES, "Ensure the accessor function works... I guess.");
    }
}
