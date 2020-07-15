<?php
declare(strict_types=1);

namespace Cadfael\Tests\Engine\Check\MySQL\Table;

use Cadfael\Engine\Check\MySQL\Table\RedundantIndexes;
use Cadfael\Engine\Entity\MySQL\Table\SchemaRedundantIndexes;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\Check\MySQL\BaseTest;

class RedundantIndexesTest extends BaseTest
{
    public function providerTableDataForSupports() {
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

    public function providerTableDataForRun() {
        $tableWithRedundantIndex = $this->createTable();
        $tableWithRedundantIndex->setSchemaRedundantIndexes(
            SchemaRedundantIndexes::createFromSys([
                "table_schema"                  => "tests",
                "table_name"                    => "table_with_unused_index",
                "redundant_index_name"          => "id_some",
                "redundant_index_columns"       => "id,some",
                "redundant_index_non_unique"    => 1,
                "dominant_index_name"           => "PRIMARY",
                "dominant_index_columns"        => "id",
                "dominant_index_non_unique"     => 0,
                "subpart_exists"                => 0,
                "sql_drop_index"                => "ALTER TABLE `tests`.`table_with_unused_index` DROP INDEX `id_some`",
            ])
        );

        return [
            [
                $tableWithRedundantIndex,
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
        $check = new RedundantIndexes();
        $this->assertEquals($check->supports($table), $result, "Ensure that the supports for $table returns $result");
    }

    /**
     * @dataProvider providerTableDataForRun
     */
    public function testRun($table, $result)
    {
        $check = new RedundantIndexes();
        $this->assertEquals($check->run($table)->getStatus(), $result, "Ensure that the run for $table returns status $result");
    }
}
