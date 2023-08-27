<?php

declare(strict_types=1);


namespace Cadfael\Tests\Engine\Check\Table;

use Cadfael\Engine\Check\Table\UnusedTable;
use Cadfael\Engine\Entity\Table\AccessInformation;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\BaseTest;

class UnusedTableTest extends BaseTest
{
    public function providerTableDataForSupports(): array
    {
        return [
            [
                $this->createTable(),
                true
            ],
            [
                $this->createVirtualTable(),
                false
            ],
        ];
    }

    public function providerTableDataForRun(): array
    {
        $table_with_read_and_write = $this->createTable();
        $table_with_read_and_write->setAccessInformation(AccessInformation::createFromIOSummary([
            'COUNT_READ' => 10,
            'COUNT_WRITE' => 10,
        ]));

        $table_with_read_only = $this->createTable();
        $table_with_read_only->setAccessInformation(AccessInformation::createFromIOSummary([
            'COUNT_READ' => 10,
            'COUNT_WRITE' => 0,
        ]));

        $table_with_write_only = $this->createTable();
        $table_with_write_only->setAccessInformation(AccessInformation::createFromIOSummary([
            'COUNT_READ' => 0,
            'COUNT_WRITE' => 10,
        ]));

        $table_with_no_access = $this->createTable();
        $table_with_no_access->setAccessInformation(AccessInformation::createFromIOSummary([
            'COUNT_READ' => 0,
            'COUNT_WRITE' => 0,
        ]));

        $empty_table_with_no_access = $this->createEmptyTable();
        $empty_table_with_no_access->setAccessInformation(AccessInformation::createFromIOSummary([
            'COUNT_READ' => 0,
            'COUNT_WRITE' => 0,
        ]));


        return [
            [
                $table_with_read_and_write,
                Report::STATUS_OK,
                []
            ],
            [
                $table_with_read_only,
                Report::STATUS_OK,
                []
            ],
            [
                $table_with_write_only,
                Report::STATUS_OK,
                []
            ],
            [
                $table_with_no_access,
                Report::STATUS_CONCERN,
                [ "Table has not been written to or read from since the last server restart." ]
            ],
            [
                // Table with no access but it's also empty
                $empty_table_with_no_access,
                null,
                []
            ],
            [
                // Table with no access information at all
                $this->createTable(),
                null,
                []
            ],
        ];
    }

    /**
     * @dataProvider providerTableDataForSupports
     */
    public function testSupports($table, $result)
    {
        $check = new UnusedTable();
        $this->assertEquals(
            $result,
            $check->supports($table),
            "Ensure that the supports for $table returns $result"
        );
    }

    /**
     * @dataProvider providerTableDataForRun
     */
    public function testRun($table, $result, $message)
    {
        $check = new UnusedTable();
        $report = $check->run($table);

        if (!$result) {
            $this->assertNull(
                $report,
                "Ensure that the report doesn't exist");
        } else {
            $this->assertEquals(
                $result,
                $report->getStatus(),
                "Ensure that the run for $table returns status $result");

            $this->assertEquals(
                $message,
                $report->getMessages(),
                "Ensure that the run for $table returns expected message");
        }
    }
}
