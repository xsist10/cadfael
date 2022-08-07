<?php
declare(strict_types=1);

use Cadfael\Engine\Check\Column\SaneAutoIncrement;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\BaseTest;
use Cadfael\Tests\Engine\Check\ColumnBuilder;

class SaneAutoIncrementTest extends BaseTest
{
    public function providerColumnData()
    {
        $builder = new ColumnBuilder();

        $tables = [
            $this->createTable(),
            $this->createVirtualTable()
        ];

        $columns = [
            // Sane primary  auto increment column
            $builder->int(10)->unsigned()->primary()->auto_increment()->generate(),
            // Unsigned primary auto increment column
            $builder->int(10)->primary()->auto_increment()->generate(),
            // Non-numeric primary auto increment column
            $builder->varchar(30)->primary()->auto_increment()->generate(),
            // Non-primary auto increment column
            $builder->int(10)->unsigned()->auto_increment()->generate(),
            // Primary generated (virtual) column
            $builder->int(10)->primary()->generated()->generate(),
            // Non auto-incrementing primary column
            $builder->int(10)->unsigned()->primary()->generate(),
        ];

        $supports_results = [
            true,
            true,
            true,
            true,
            false,
            false,
            false,
            false,
            false,
            false,
            false,
            false
        ];

        $run_results = [
            Report::STATUS_OK,
            Report::STATUS_WARNING,
            Report::STATUS_WARNING,
            Report::STATUS_WARNING,
            Report::STATUS_WARNING,
        ];

        $configurations = [];
        $offset = 0;

        foreach ($tables as $table) {
            foreach ($columns as $column) {
                $tmp_table = clone $table;

                $tmp_column = clone $column;
                $tmp_table->setColumns($tmp_column);

                $configurations[] = [
                    $tmp_column,
                    $supports_results[$offset],
                    $run_results[$offset] ?? null
                ];
                $offset++;
            }
        }

        return $configurations;
    }

    /**
     * Refactor this into normal tests....
     *
     * @dataProvider providerColumnData
     */
    public function testSupports($column, $supports, $status)
    {
        $check = new SaneAutoIncrement();
        $this->assertEquals($supports, $check->supports($column), "Ensure that the supports for $column returns $supports");
    }

    /**
     * Refactor this into normal tests....
     *
     * @dataProvider providerColumnData
     */
    public function testRun($column, $supports, $status)
    {
        $check = new SaneAutoIncrement();
        // Only run checks for these tests
        if ($supports) {
            $report = $check->run($column);
            $this->assertEquals($status, $report->getStatus(), "Ensure that the run for $column returns status $status");
        } else {
            $this->assertTrue(true);
        }
    }

    public function testRunForCompoundKey()
    {
        $builder = new ColumnBuilder();
        $realSaneAutoIncrementColumn = $builder->int(10)->unsigned()->primary()->auto_increment()->generate();

        $table = $this->createTable();
        $table->setColumns(
            $realSaneAutoIncrementColumn,
            $builder->int(10)->unsigned()->primary()->generate()
        );

        $check = new SaneAutoIncrement();
        $report = $check->run($realSaneAutoIncrementColumn);
        $this->assertEquals(Report::STATUS_WARNING, $report->getStatus(), "We should not have a compound PRIMARY KEY where one of the columns is auto_incrementing.");
    }
}
