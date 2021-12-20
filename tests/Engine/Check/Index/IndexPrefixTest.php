<?php

namespace Cadfael\Tests\Engine\Check\Index;

use Cadfael\Engine\Check\Index\IndexPrefix;
use Cadfael\Engine\Entity\Index;
use Cadfael\Engine\Entity\Table;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\Check\BaseTest;
use Cadfael\Tests\Engine\Check\ColumnBuilder;
use Cadfael\Tests\Engine\Check\IndexBuilder;

class IndexPrefixTest extends BaseTest
{
    protected Index $nonStringIndex;
    protected Index $uniqueIndex;
    protected Index $shortStringIndex;
    protected Index $longStringPrefixedIndex;
    protected Index $zeroCardinalityIndex;
    protected Index $longStringIndex;

    public function setUp(): void
    {
        $builder = new IndexBuilder();

        // We need:
        // * A unique index
        $this->uniqueIndex = $builder->name('unique_index')->isUnique(true)->generate();
        $this->uniqueIndex->getColumns()[0]->setCardinality(1);

        // * An index without string columns
        $this->nonStringIndex = $builder->name('non_string_index')->generate();
        $this->nonStringIndex->getColumns()[0]->setCardinality(1);

        // * An index with string columns that are under 12 characters
        $this->shortStringIndex = $builder
            ->name('short_string_index')
            ->setColumn((new ColumnBuilder())->varchar(10)->generate())
            ->generate();

        // * An index with string columns that are over 12 characters and prefixed already
        $this->longStringPrefixedIndex = $builder
            ->name('long_string_prefixed_index')
            ->setColumn((new ColumnBuilder())->varchar(255)->generate())
            ->setSubPart(12)
            ->generate();

        // * An index with string columns and 0 cardinality
        $this->zeroCardinalityIndex = $builder
            ->name('long_string_zero_cardinality_index')
            ->setColumn((new ColumnBuilder())->varchar(255)->generate())
            ->generate();
        $column = $this->zeroCardinalityIndex->getColumns()[0];
        $column->setCardinality(1);
        $column->setTable($this->createTable([ 'TABLE_ROWS' => 0 ]));

        // * An index with string columns that are over 12 characters and is not prefixed
        $this->longStringIndex = $builder
            ->name('long_string_zero_cardinality_index')
            ->setColumn((new ColumnBuilder())->varchar(255)->generate())
            ->generate();
        $column = $this->longStringIndex->getColumns()[0];
        $column->setCardinality(1000);
        $column->setTable($this->createTable([ 'TABLE_ROWS' => 10000 ]));
    }

    public function testSupports()
    {
        $check = new IndexPrefix();
        $this->assertTrue($check->supports($this->uniqueIndex), "Ensure that the supports for a column returns true.");
        $this->assertTrue($check->supports($this->nonStringIndex), "Ensure that the supports for a column returns true.");
        $this->assertTrue($check->supports($this->shortStringIndex), "Ensure that the supports for a column returns true.");
        $this->assertTrue($check->supports($this->longStringPrefixedIndex), "Ensure that the supports for a column returns true.");
        $this->assertTrue($check->supports($this->zeroCardinalityIndex), "Ensure that the supports for a column returns true.");
        $this->assertTrue($check->supports($this->longStringIndex), "Ensure that the supports for a column returns true.");
    }

    public function testRun()
    {
        $check = new IndexPrefix();

        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($this->uniqueIndex)->getStatus(),
            "Ensure that an OK report is returned for a unique index with any table."
        );

        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($this->nonStringIndex)->getStatus(),
            "Ensure that an OK report is returned for a non string index with any table."
        );

        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($this->shortStringIndex)->getStatus(),
            "Ensure that an OK report is returned for a short string index with any table."
        );

        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($this->longStringPrefixedIndex)->getStatus(),
            "Ensure that an OK report is returned for a long string index that is already prefixed with any table."
        );

        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($this->zeroCardinalityIndex)->getStatus(),
            "Ensure that an OK report is returned for a long string index without cardinality with any table."
        );

        $this->assertEquals(
            Report::STATUS_CONCERN,
            $check->run($this->longStringIndex)->getStatus(),
            "Ensure that a CONCERN report is returned for a long string index with any table."
        );
    }
}
