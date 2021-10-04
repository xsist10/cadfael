<?php
declare(strict_types=1);

namespace Cadfael\Tests\Engine\Check\Database;

use Cadfael\Engine\Check\Database\RequirePrimaryKey;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\Check\BaseTest;

class RequirePrimaryKeyTest extends BaseTest
{
    private array $databases;

    public function setUp(): void
    {
        $this->databases = [
            '5.7' => $this->createDatabase([ 'version' => '5.7.0' ]),
            '8.0_NOT_SET' => $this->createDatabase([ 'version' => '8.0.0', 'sql_require_primary_key' => null]),
            '8.0_OFF' => $this->createDatabase([ 'version' => '8.0.0', 'sql_require_primary_key' => 'OFF' ]),
            '8.0_ON' => $this->createDatabase([ 'version' => '8.0.0', 'sql_require_primary_key' => 'ON' ]),
        ];
    }

    public function testSupports()
    {
        $check = new RequirePrimaryKey();

        foreach ($this->databases as $database) {
            $this->assertTrue(
                $check->supports($database),
                "Ensure that we care about all databases."
            );
        }
    }

    public function testRun()
    {
        $check = new RequirePrimaryKey();

        $this->assertEquals(
          Report::STATUS_OK,
          $check->run($this->databases['5.7'])->getStatus(),
	        "Ensure we return " . Report::STATUS_OK . " for MySQL versions <8."
	       );

        $this->assertEquals(
          Report::STATUS_WARNING,
          $check->run($this->databases['8.0_NOT_SET'])->getStatus(),
	        "Ensure we return " . Report::STATUS_WARNING . " for MySQL versions >=8 with sql_require_primary_key not set."
	      );

        $this->assertEquals(
          Report::STATUS_WARNING,
          $check->run($this->databases['8.0_OFF'])->getStatus(),
	        "Ensure we return " . Report::STATUS_WARNING . " for MySQL versions >=8 with sql_require_primary_key set to OFF."
	      );

        $this->assertEquals(
          Report::STATUS_OK,
          $check->run($this->databases['8.0_ON'])->getStatus(),
	        "Ensure we return " . Report::STATUS_OK . " for MySQL versions >=8 with sql_require_primary_key set to ON."
	      );
    }
}
