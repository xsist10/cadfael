<?php
declare(strict_types=1);

namespace Cadfael\Tests\Engine\Check\Database;

use Cadfael\Engine\Check\Database\UnsupportedVersion;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\BaseTest;

class UnsupportedVersionTest extends BaseTest
{
    private array $databases;

    protected function setUp(): void
    {
        $this->databases = [
            '1.1' => $this->createDatabase([ 'version' => '1.1.0' ]),
            '5.1' => $this->createDatabase([ 'version' => '5.1.0' ]),
            '5.5' => $this->createDatabase([ 'version' => '5.5.0' ]),
            '5.6' => $this->createDatabase([ 'version' => '5.6.0' ]),
            '5.7' => $this->createDatabase([ 'version' => '5.7.0' ]),
            '8.0' => $this->createDatabase([ 'version' => '8.0.0' ]),
            '8.1' => $this->createDatabase([ 'version' => '8.1.0' ]),
        ];
    }

    public function testSupports()
    {
        $check = new UnsupportedVersion();

        foreach ($this->databases as $database) {
            $this->assertTrue(
                $check->supports($database),
                "Ensure that we care about all databases."
            );
        }
    }

    public function testRun()
    {
        $check = new UnsupportedVersion(new \DateTimeImmutable("2026-01-01"));

        $expected_results = [
            '1.1' => Report::STATUS_CONCERN,
            '5.1' => Report::STATUS_CRITICAL,
            '5.5' => Report::STATUS_CRITICAL,
            '5.6' => Report::STATUS_CRITICAL,
            '5.7' => Report::STATUS_CRITICAL,
            '8.0' => Report::STATUS_WARNING,
            '8.1' => Report::STATUS_OK,
        ];

        foreach ($expected_results as $version => $status) {
            $report = $check->run($this->databases[$version]);
            $this->assertEquals(
                $status,
                $report->getStatus(),
                "Ensure we return " . Report::STATUS_LABEL[$status] . " ($status) for version $version."
            );
        }
    }
}
