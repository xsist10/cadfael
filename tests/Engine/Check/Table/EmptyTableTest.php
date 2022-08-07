<?php
declare(strict_types=1);

use Cadfael\Engine\Check\Table\EmptyTable;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\BaseTest;
use Cadfael\Tests\Engine\Check\ColumnBuilder;

class EmptyTableTest extends BaseTest
{
    public function providerTableDataForSupports() {
        return [
            [
                $this->createEmptyTable(),
                true
            ],
            [
                $this->createEmptyTable([ "DATA_FREE" => 110 ]),
                true
            ],
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

    public function providerTableDataForRun() {
        $builder = new ColumnBuilder();
        $column = $builder->int(10)
            ->unsigned()
            ->primary()
            ->auto_increment()
            ->generate();
        $table = $this->createEmptyTable();
        $table->setColumns($column);

        $column = $builder->int(10)
            ->unsigned()
            ->primary()
            ->auto_increment()
            ->generate();
        $table_with_inserts = $this->createEmptyTable();
        $table_with_inserts->setColumns($column);
        $auto_increment = $table_with_inserts->getSchemaAutoIncrementColumn();
        $auto_increment->auto_increment = 10;

        return [
            [
                $table,
                Report::STATUS_WARNING
            ],
            [
                $table_with_inserts,
                Report::STATUS_CONCERN
            ],
            [
                $this->createEmptyTable([ "DATA_FREE" => 110 ]),
                Report::STATUS_CONCERN
            ],
            [
                $this->createTable(),
                Report::STATUS_OK
            ],
        ];
    }

    /**
     * @dataProvider providerTableDataForSupports
     */
    public function testSupports($table, $result)
    {
        $check = new EmptyTable();
        $this->assertEquals($check->supports($table), $result, "Ensure that the supports for $table returns $result");
    }

    /**
     * @dataProvider providerTableDataForRun
     */
    public function testRun($table, $result)
    {
        $check = new EmptyTable();
        $this->assertEquals($check->run($table)->getStatus(), $result, "Ensure that the run for $table returns status $result");
    }
}
