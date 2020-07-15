<?php
declare(strict_types=1);

use Cadfael\Engine\Check\MySQL\Table\AutoIncrementCapacity;
use Cadfael\Engine\Entity\MySQL\Table\SchemaAutoIncrementColumns;
use Cadfael\Engine\Exception\MissingSysData;
use Cadfael\Engine\Report;

use Cadfael\Tests\Engine\Check\MySQL\BaseTest;

class AutoIncrementCapacityTest extends BaseTest
{
    public function providerTableData() {
        $emptyLargeCapacity = $this->createTable();
        $emptyLargeCapacity->setSchemaAutoIncrementColumns(SchemaAutoIncrementColumns::createFromSys([
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
        $midLargeCapacity->setSchemaAutoIncrementColumns(SchemaAutoIncrementColumns::createFromSys([
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
        $fullLargeCapacity->setSchemaAutoIncrementColumns(SchemaAutoIncrementColumns::createFromSys([
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
        $emptySmallCapacity->setSchemaAutoIncrementColumns(SchemaAutoIncrementColumns::createFromSys([
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
        $midSmallCapacity->setSchemaAutoIncrementColumns(SchemaAutoIncrementColumns::createFromSys([
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
        $fullSmallCapacity->setSchemaAutoIncrementColumns(SchemaAutoIncrementColumns::createFromSys([
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
            [ $emptyLargeCapacity,  Report::STATUS_OK ],
            [ $midLargeCapacity,    Report::STATUS_WARNING ],
            [ $fullLargeCapacity,   Report::STATUS_CRITICAL ],
            [ $emptySmallCapacity,  Report::STATUS_OK ],
            [ $midSmallCapacity,    Report::STATUS_WARNING ],
            [ $fullSmallCapacity,   Report::STATUS_WARNING ],
        ];
    }

    /**
     * @dataProvider providerTableData
     */
    public function testSupports($table, $status)
    {
        $check = new AutoIncrementCapacity();
        $this->assertTrue($check->supports($table));
    }

    /**
     * @dataProvider providerTableData
     */
    public function testRun($table, $status)
    {
        $check = new AutoIncrementCapacity();
        $report = $check->run($table);
        $this->assertEquals($status, $report->getStatus(), "Ensure that the run for $table returns status $status");

        $data = $report->getData();
        if ($data) {
            $this->assertEquals($table->schema_auto_increment_columns->max_value, $data['total']);
            $this->assertEquals($table->schema_auto_increment_columns->auto_increment, $data['used']);
        }
    }

    public function testRunWithMissingSysData()
    {
        $this->expectException(MissingSysData::class);

        $check = new AutoIncrementCapacity();
        $check->run($this->createTable());
    }
}
