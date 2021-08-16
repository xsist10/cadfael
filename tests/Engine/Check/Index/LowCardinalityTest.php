<?php

namespace Cadfael\Tests\Engine\Check\Index;

use Cadfael\Engine\Check\Index\LowCardinality;
use Cadfael\Engine\Entity\Column;
use Cadfael\Engine\Entity\Index;
use Cadfael\Engine\Entity\Table;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\Check\BaseTest;
use Cadfael\Tests\Engine\Check\ColumnBuilder;
use PHPUnit\Framework\TestCase;

class LowCardinalityTest extends BaseTest
{
    protected Column $highCardinalityColumn;
    protected Column $lowCardinalityColumn;

    protected Index $highCardinalityIndex;
    protected Index $lowCardinalityIndex;
    protected Index $uniqueIndex;

    protected Table $largeTable;
    protected Table $mediumTable;
    protected Table $smallTable;

    public function setUp(): void
    {
        $this->largeTable = $this->createTable([ 'TABLE_ROWS' => 500_000 ]);
        $this->mediumTable = $this->createTable([ 'TABLE_ROWS' => 10_000 ]);
        $this->smallTable = $this->createTable([ 'TABLE_ROWS' => 50 ]);

        $builder = new ColumnBuilder();
        $this->highCardinalityColumn = $builder->name("high_cardinality_value")->generate();
        $this->highCardinalityColumn->setCardinality(100_000);
        $this->lowCardinalityColumn = $builder->name("low_cardinality_value")->generate();
        $this->lowCardinalityColumn->setCardinality(10);

        $this->highCardinalityIndex = new Index('high_cardinality_index');
        $this->highCardinalityIndex->setColumns($this->highCardinalityColumn);
        $this->highCardinalityIndex->setUnique(false);

        $this->lowCardinalityIndex = new Index('low_cardinality_index');
        $this->lowCardinalityIndex->setColumns($this->lowCardinalityColumn);
        $this->lowCardinalityIndex->setUnique(false);

        $this->uniqueIndex = new Index('unique_index');
        $this->uniqueIndex->setColumns($this->highCardinalityColumn);
        $this->uniqueIndex->setUnique(true);
    }

    public function testSupports()
    {
        $check = new LowCardinality();
        $this->assertTrue($check->supports($this->highCardinalityIndex), "Ensure that the supports for a column returns true.");
        $this->assertTrue($check->supports($this->lowCardinalityIndex), "Ensure that the supports for a column returns true.");
        $this->assertTrue($check->supports($this->uniqueIndex), "Ensure that the supports for a column returns true.");
    }

    public function testRun()
    {
        $check = new LowCardinality();
        $this->highCardinalityIndex->setTable($this->largeTable);
        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($this->highCardinalityIndex)->getStatus(),
            "Ensure that an OK report is returned for $this->highCardinalityIndex with a large table."
        );
        $this->highCardinalityColumn->setTable($this->mediumTable);
        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($this->highCardinalityIndex)->getStatus(),
            "Ensure that an OK report is returned for $this->highCardinalityIndex with a medium table."
        );
        $this->highCardinalityColumn->setTable($this->smallTable);
        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($this->highCardinalityIndex)->getStatus(),
            "Ensure that an OK report is returned for $this->highCardinalityIndex with a small table."
        );

        $this->lowCardinalityIndex->setTable($this->largeTable);
        $this->assertEquals(
            Report::STATUS_WARNING,
            $check->run($this->lowCardinalityIndex)->getStatus(),
            "Ensure that an OK report is returned for $this->lowCardinalityIndex with a large table."
        );
        $this->lowCardinalityIndex->setTable($this->mediumTable);
        $this->assertEquals(
            Report::STATUS_CONCERN,
            $check->run($this->lowCardinalityIndex)->getStatus(),
            "Ensure that an OK report is returned for $this->lowCardinalityIndex with a medium table."
        );
        $this->lowCardinalityIndex->setTable($this->smallTable);
        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($this->lowCardinalityIndex)->getStatus(),
            "Ensure that an OK report is returned for $this->lowCardinalityIndex with a small table."
        );

        $this->uniqueIndex->setTable($this->largeTable);
        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($this->uniqueIndex)->getStatus(),
            "Ensure that an OK report is returned for $this->uniqueIndex with any table."
        );
    }
}
