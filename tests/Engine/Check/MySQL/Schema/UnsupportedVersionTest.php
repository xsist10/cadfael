<?php
declare(strict_types=1);

namespace Cadfael\Tests\Engine\Check\MySQL\Schema;

use Cadfael\Engine\Check\MySQL\Schema\UnsupportedVersion;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\Check\MySQL\BaseTest;

class UnsupportedVersionTest extends BaseTest
{
    private array $schemas;

    public function setUp(): void
    {
        $this->schemas = [
            '1.1' => $this->createSchema([ 'version' => '1.1.0' ]),
            '5.1' => $this->createSchema([ 'version' => '5.1.0' ]),
            '5.5' => $this->createSchema([ 'version' => '5.5.0' ]),
            '5.6' => $this->createSchema([ 'version' => '5.6.0' ]),
            '5.7' => $this->createSchema([ 'version' => '5.7.0' ]),
            '8.0' => $this->createSchema([ 'version' => '8.0.0' ]),
        ];
    }

    public function testSupports()
    {
        $check = new UnsupportedVersion();

        foreach ($this->schemas as $schema) {
            $this->assertTrue(
                $check->supports($schema),
                "Ensure that we care about all schemas."
            );
        }
    }

    public function testRun()
    {
        $check = new UnsupportedVersion();

        $expected_results = [
            '1.1' => Report::STATUS_CONCERN,
            '5.1' => Report::STATUS_CRITICAL,
            '5.5' => Report::STATUS_CRITICAL,
            '5.6' => Report::STATUS_OK,
            '5.7' => Report::STATUS_OK,
            '8.0' => Report::STATUS_OK,
        ];

        foreach ($expected_results as $version => $status) {
            $this->assertEquals(
                $status,
                $check->run($this->schemas[$version])->getStatus(),
                "Ensure we return " . Report::STATUS_LABEL[$status] . " ($status) for version $version."
            );
        }
    }
}
