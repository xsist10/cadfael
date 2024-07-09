<?php
declare(strict_types=1);

use Cadfael\Engine\Check\Table\AutoIncrementCapacity;
use Cadfael\Engine\Entity\Table\SchemaAutoIncrementColumn;
use Cadfael\Engine\Report;

use Cadfael\Tests\Engine\BaseTest;

class AutoIncrementCapacityTest extends BaseTest
{
    public function providerTableData() {
        $withoutAutoIncrement = $this->createTable();

        $emptyLargeCapacity = $this->createTable();
        $emptyLargeCapacity->setSchemaAutoIncrementColumn(SchemaAutoIncrementColumn::createFromSys([
            'table_schema'          => 'tests',
            'table_name'            => 'table_with_signed_autoincrement',
            'column_name'           => 'id',
            'data_type'             => 'int',
            'column_type'           => 'int(11)',
            'is_signed'             => 1,
            'is_unsigned'           => 0,
            'max_value'             => 2147483647,
            'auto_increment'        => null,
            'auto_increment_ratio'  => 0.0000,
        ]));

        $midLargeCapacity = $this->createTable();
        $midLargeCapacity->setSchemaAutoIncrementColumn(SchemaAutoIncrementColumn::createFromSys([
            'table_schema'          => 'tests',
            'table_name'            => 'table_with_signed_autoincrement',
            'column_name'           => 'id',
            'data_type'             => 'int',
            'column_type'           => 'int(11)',
            'is_signed'             => 1,
            'is_unsigned'           => 0,
            'max_value'             => 2147483647,
            'auto_increment'        => 1331449861,
            'auto_increment_ratio'  => 0.6200,
        ]));

        $fullLargeCapacity = $this->createTable();
        $fullLargeCapacity->setSchemaAutoIncrementColumn(SchemaAutoIncrementColumn::createFromSys([
            'table_schema'          => 'tests',
            'table_name'            => 'table_with_signed_autoincrement',
            'column_name'           => 'id',
            'data_type'             => 'int',
            'column_type'           => 'int(11)',
            'is_signed'             => 1,
            'is_unsigned'           => 0,
            'max_value'             => 2147483647,
            'auto_increment'        => 1825361100,
            'auto_increment_ratio'  => 0.8500,
        ]));

        $emptySmallCapacity = $this->createTable();
        $emptySmallCapacity->setSchemaAutoIncrementColumn(SchemaAutoIncrementColumn::createFromSys([
            'table_schema'          => 'tests',
            'table_name'            => 'table_with_signed_autoincrement',
            'column_name'           => 'id',
            'data_type'             => 'int',
            'column_type'           => 'int(11)',
            'is_signed'             => 1,
            'is_unsigned'           => 0,
            'max_value'             => 256,
            'auto_increment'        => 0,
            'auto_increment_ratio'  => 0.0000,
        ]));

        $midSmallCapacity = $this->createTable();
        $midSmallCapacity->setSchemaAutoIncrementColumn(SchemaAutoIncrementColumn::createFromSys([
            'table_schema'          => 'tests',
            'table_name'            => 'table_with_signed_autoincrement',
            'column_name'           => 'id',
            'data_type'             => 'int',
            'column_type'           => 'int(11)',
            'is_signed'             => 1,
            'is_unsigned'           => 0,
            'max_value'             => '256',
            'auto_increment'        => 160,
            'auto_increment_ratio'  => 0.6250,
        ]));

        $fullSmallCapacity = $this->createTable();
        $fullSmallCapacity->setSchemaAutoIncrementColumn(SchemaAutoIncrementColumn::createFromSys([
            'table_schema'          => 'tests',
            'table_name'            => 'table_with_signed_autoincrement',
            'column_name'           => 'id',
            'data_type'             => 'int',
            'column_type'           => 'int(11)',
            'is_signed'             => 1,
            'is_unsigned'           => 0,
            'max_value'             => '256',
            'auto_increment'        => 210,
            'auto_increment_ratio'  => 0.8203,
        ]));

        return [
            [ $withoutAutoIncrement, false, null ],
            [ $emptyLargeCapacity,   true,  Report::STATUS_OK ],
            [ $midLargeCapacity,     true,  Report::STATUS_WARNING ],
            [ $fullLargeCapacity,    true,  Report::STATUS_CRITICAL ],
            [ $emptySmallCapacity,   true,  Report::STATUS_OK ],
            [ $midSmallCapacity,     true,  Report::STATUS_WARNING ],
            [ $fullSmallCapacity,    true,  Report::STATUS_WARNING ],
        ];
    }

    /**
     * @dataProvider providerTableData
     */
    public function testSupports($table, $supports, $status)
    {
        $check = new AutoIncrementCapacity();
        $this->assertSame($supports, $check->supports($table));
    }

    /**
     * @dataProvider providerTableData
     */
    public function testRun($table, $supports, $status)
    {
        $check = new AutoIncrementCapacity();
        $report = $check->run($table);
        if ($report) {
            $this->assertEquals($status, $report->getStatus(), "Ensure that the run for $table returns status $status");

            $data = $report->getData();
            if ($data) {
                $this->assertEquals($table->schema_auto_increment_column->max_value, $data['total']);
                $this->assertEquals($table->schema_auto_increment_column->auto_increment, $data['used']);
            }
        } else {
            $this->assertEquals($status, $report, "Ensure that the report should actually be null.");
        }
    }
}
