<?php
declare(strict_types=1);

namespace Cadfael\Tests\Engine\Check\Table;

use Cadfael\Engine\Check\Table\RedundantIndexes;
use Cadfael\Engine\Entity\Table\SchemaRedundantIndex;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\BaseTest;
use Cadfael\Tests\Engine\Check\IndexBuilder;

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
        $builder = new IndexBuilder();
        $indexes = [
            "id_some" => $builder->name("id_some")->generate(),
            "PRIMARY" => $builder->name("PRIMARY")->generate()
        ];

        $tableWithRedundantIndex = $this->createTable();
        $tableWithRedundantIndex->setSchemaRedundantIndexes(
            SchemaRedundantIndex::createFromSys(
                $indexes,
                [
                    "table_schema"                  => "tests",
                    "table_name"                    => "table_with_unused_index",
                    "redundant_index_name"          => "id_some",
                    "redundant_index_columns"       => "id,some",
                    "redundant_index_non_unique"    => 1,
                    "dominant_index_name"           => "PRIMARY",
                    "dominant_index_columns"        => "id",
                    "dominant_index_non_unique"     => 1,
                    "subpart_exists"                => 0,
                    "sql_drop_index"                => "ALTER TABLE `tests`.`table_with_unused_index` DROP INDEX `id_some`",
                ]
            )
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
        $report = $check->run($table);
        $this->assertEquals($report->getStatus(), $result, "Ensure that the run for $table returns status $result");
        if ($report->getStatus() != Report::STATUS_OK) {
            $this->assertStringNotContainsString('UNIQUE', implode("\n", $report->getMessages()));
            $table->schema_redundant_indexes[0]->redundant_index->setUnique(true);
            $this->assertStringContainsString('UNIQUE', implode("\n", $check->run($table)->getMessages()));
        }
    }
}
