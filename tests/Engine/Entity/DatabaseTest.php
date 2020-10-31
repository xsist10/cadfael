<?php
declare(strict_types=1);

namespace Cadfael\Tests\Engine\Entity;

use Cadfael\Engine\Entity\Database;
use Cadfael\Engine\Entity\Schema;
use Cadfael\Engine\Entity\Table;
use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase
{
    protected Database $database;

    const VERSION = '5.7';
    const VARIABLES = [
        'version' => self::VERSION
    ];

    protected function setUp(): void
    {
        $this->database = new Database(null);
        $this->database->setVariables(self::VARIABLES);
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
