<?php
declare(strict_types=1);

use Cadfael\Engine\Check\Table\EmptyTable;
use Cadfael\Engine\Entity\Table\InnoDbTable;
use Cadfael\Engine\Entity\Tablespace;
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
        $table = $this->createEmptyTable(['TABLE_NAME' => 'empty_table']);
        $table->setColumns($column);

        $column = $builder->int(10)
            ->unsigned()
            ->primary()
            ->auto_increment()
            ->generate();
        $table_with_inserts = $this->createEmptyTable(['TABLE_NAME' => 'empty_table_with_inserts']);
        $table_with_inserts->setColumns($column);
        $auto_increment = $table_with_inserts->getSchemaAutoIncrementColumn();
        $auto_increment->auto_increment = 10;

        $table_with_data_free = $this->createEmptyTable(['TABLE_NAME' => 'empty_table_with_data_free', 'DATA_FREE' => 110]);
        $table_with_data_free_in_tablespace = $this->createEmptyTable(['TABLE_NAME' => 'empty_table_with_data_free_in_tablespace', 'DATA_FREE' => 110]);
        $table_with_data_free_in_tablespace->setInnoDbTable(InnoDbTable::createFromInformationSchema([
            'TABLE_ID' => 2425,
            'SPACE' => 0,
            'NAME' => 'test/test',
            'FLAG' => 161,
            'N_COLS' => 5,
            'ROW_FORMAT' => 'Dynamic',
            'ZIP_PAGE_SIZE' => 0,
            'SPACE_TYPE' => 'System',
            'INSTANT_COLS' => 0
        ]));

        return [
            [
                $table,
                Report::STATUS_INFO,
                [ 'Table contains no records.' ]
            ],
            [
                $table_with_inserts,
                Report::STATUS_INFO,
                [
                    "Table is empty but previously had records inserted.",
                    "It is possible it is used as a some form of queue or has had all records deleted."
                ]
            ],
            [
                $table_with_data_free,
                Report::STATUS_INFO,
                [
                    "Table is empty but has allocated free space.",
                    "It is possible it is used as a some form of queue or has had all records deleted."
                ]
            ],
            [
                $table_with_data_free_in_tablespace,
                Report::STATUS_INFO,
                [
                    "Table is empty but has allocated free space.",
                    "This table is in a shared tablespace so this doesn't mean much."
                ]
            ],
            [
                $this->createTable(['TABLE_NAME' => 'table_with_rows']),
                Report::STATUS_OK,
                []
            ],
        ];
    }

    /**
     * @dataProvider providerTableDataForSupports
     */
    public function testSupports($table, $result)
    {
        $check = new EmptyTable();
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
        $check = new EmptyTable();
        $report = $check->run($table);

        $this->assertEquals(
            $result,
            $report->getStatus(),
            "Ensure that the run for $table returns status $result")
        ;

        $this->assertEquals(
            $message,
            $report->getMessages(),
            "Ensure that the run for $table returns expected message")
        ;
    }
}
