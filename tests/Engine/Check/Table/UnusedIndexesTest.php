<?php
declare(strict_types=1);

use Cadfael\Engine\Check\Table\UnusedIndexes;
use Cadfael\Engine\Entity\Table\UnusedIndex;
use Cadfael\Engine\Report;

use Cadfael\Tests\Engine\BaseTest;
use Cadfael\Tests\Engine\Check\IndexBuilder;

class UnusedIndexesTest extends BaseTest
{
    public function providerTableDataForRun() {
        $builder = new IndexBuilder();
        $indexes = [
            "id_some" => $builder->name("id_some")->generate(),
            "PRIMARY" => $builder->name("PRIMARY")->generate(),
            "UNIQUE"  => $builder->name("UNIQUE")->isUnique(true)->generate()
        ];

        $tableWithUnusedIndex = $this->createTable();
        $tableWithUnusedIndex->setUnusedIndexes(new UnusedIndex($indexes['id_some']));

        $tableWithUnusedUniqueIndex = $this->createTable();
        $tableWithUnusedUniqueIndex->setUnusedIndexes(new UnusedIndex($indexes['UNIQUE']));

        return [
            [
                $tableWithUnusedIndex,
                Report::STATUS_CONCERN,
                [
                    "Unused index id_some.",
                    "This check only indicates that an index has not been used since the server started."
                ]
            ],
            [
                $tableWithUnusedUniqueIndex,
                Report::STATUS_CONCERN,
                [
                    "Unused index UNIQUE.",
                    "However index UNIQUE is a UNIQUE constraint",
                    "This check only indicates that an index has not been used since the server started.",
                ]
            ],
            [
                $this->createTable(),
                Report::STATUS_OK,
                [ "No unused indexes found." ]
            ],
        ];
    }

    public function testSupports()
    {
        $check = new UnusedIndexes();
        $this->assertTrue($check->supports($this->createTable()), "Ensure that tables are supported.");
        $this->assertFalse($check->supports($this->createSchema()), "Ensure that non-table entities are not supported.");
    }

    /**
     * @dataProvider providerTableDataForRun
     */
    public function testRun($table, $status, $messages)
    {
        $check = new UnusedIndexes();
        $report = $check->run($table);
        $this->assertEquals($status, $report->getStatus(), "Ensure report status matches expected status.");
        $this->assertEquals($messages, $report->getMessages(), "Ensure report messages matches expected messages.");
    }
}
