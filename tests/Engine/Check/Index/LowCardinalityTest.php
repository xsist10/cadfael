<?php

namespace Cadfael\Tests\Engine\Check\Index;

use Cadfael\Engine\Check\Index\LowCardinality;
use Cadfael\Engine\Entity\Column;
use Cadfael\Engine\Entity\Index;
use Cadfael\Engine\Entity\Table;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\Check\BaseTest;
use Cadfael\Tests\Engine\Check\IndexBuilder;

class LowCardinalityTest extends BaseTest
{
    protected Column $highCardinalityColumn;
    protected Column $lowCardinalityColumn;

    protected Index $highCardinalityIndex;
    protected Index $lowCardinalityIndex;
    protected Index $uniqueIndex;
    protected Index $brokenCardinalityIndex;

    protected Table $largeTable;
    protected Table $mediumTable;
    protected Table $smallTable;

    public function setUp(): void
    {
        $this->largeTable = $this->createTable([ 'TABLE_ROWS' => 500_000 ]);
        $this->mediumTable = $this->createTable([ 'TABLE_ROWS' => 10_000 ]);
        $this->smallTable = $this->createTable([ 'TABLE_ROWS' => 50 ]);

        $builder = new IndexBuilder();

        $this->highCardinalityIndex = $builder->name('high_cardinality_index')->generate();
        $this->highCardinalityIndex->getColumns()[0]->setCardinality(100_000);

        $this->lowCardinalityIndex = $builder->name('low_cardinality_index')->generate();
        $this->lowCardinalityIndex->getColumns()[0]->setCardinality(10);

        $this->uniqueIndex = $builder->name('unique_index')->isUnique(true)->generate();
        $this->uniqueIndex->getColumns()[0]->setCardinality(100_000);

        $this->brokenCardinalityIndex = $builder->name('broken_cardinality')->generate();
        $this->brokenCardinalityIndex->getColumns()[0]->setCardinality(0);
        $this->brokenCardinalityIndex->setTable($this->smallTable);
        $this->brokenCardinalityIndex->getColumns()[0]->setTable($this->smallTable);
    }

    public function testSupports()
    {
        $check = new LowCardinality();
        $this->assertTrue($check->supports($this->highCardinalityIndex), "Ensure that the supports for a column returns true.");
        $this->assertTrue($check->supports($this->lowCardinalityIndex), "Ensure that the supports for a column returns true.");
        $this->assertTrue($check->supports($this->uniqueIndex), "Ensure that the supports for a column returns true.");
        $this->assertTrue($check->supports($this->brokenCardinalityIndex), "Ensure that the supports for a column returns true.");
    }

    public function testRun()
    {
        $check = new LowCardinality();

        $this->highCardinalityIndex->setTable($this->largeTable);
        $this->highCardinalityIndex->getColumns()[0]->setTable($this->largeTable);
        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($this->highCardinalityIndex)->getStatus(),
            "Ensure that an OK report is returned for $this->highCardinalityIndex with a large table."
        );

        $this->highCardinalityIndex->setTable($this->mediumTable);
        $this->highCardinalityIndex->getColumns()[0]->setTable($this->mediumTable);
        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($this->highCardinalityIndex)->getStatus(),
            "Ensure that an OK report is returned for $this->highCardinalityIndex with a medium table."
        );

        $this->highCardinalityIndex->setTable($this->smallTable);
        $this->highCardinalityIndex->getColumns()[0]->setTable($this->smallTable);
        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($this->highCardinalityIndex)->getStatus(),
            "Ensure that an OK report is returned for $this->highCardinalityIndex with a small table."
        );

        $this->lowCardinalityIndex->setTable($this->largeTable);
        $this->lowCardinalityIndex->getColumns()[0]->setTable($this->largeTable);
        $this->assertEquals(
            Report::STATUS_WARNING,
            $check->run($this->lowCardinalityIndex)->getStatus(),
            "Ensure that an OK report is returned for $this->lowCardinalityIndex with a large table."
        );

        $this->lowCardinalityIndex->setTable($this->mediumTable);
        $this->lowCardinalityIndex->getColumns()[0]->setTable($this->mediumTable);
        $this->assertEquals(
            Report::STATUS_CONCERN,
            $check->run($this->lowCardinalityIndex)->getStatus(),
            "Ensure that an OK report is returned for $this->lowCardinalityIndex with a medium table."
        );

        $this->lowCardinalityIndex->setTable($this->smallTable);
        $this->lowCardinalityIndex->getColumns()[0]->setTable($this->smallTable);
        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($this->lowCardinalityIndex)->getStatus(),
            "Ensure that an OK report is returned for $this->lowCardinalityIndex with a small table."
        );

        $this->uniqueIndex->setTable($this->largeTable);
        $this->uniqueIndex->getColumns()[0]->setTable($this->largeTable);
        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($this->uniqueIndex)->getStatus(),
            "Ensure that an OK report is returned for $this->uniqueIndex with any table."
        );

        $this->assertEquals(
            Report::STATUS_INFO,
            $check->run($this->brokenCardinalityIndex)->getStatus(),
            "Ensure that an INFO report is returned for $this->brokenCardinalityIndex with any table."
        );

    }
}
