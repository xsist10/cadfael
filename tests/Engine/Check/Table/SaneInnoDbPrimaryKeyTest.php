<?php
declare(strict_types=1);

use Cadfael\Engine\Check\Table\SaneInnoDbPrimaryKey;
use Cadfael\Engine\Report;

use Cadfael\Tests\Engine\BaseTest;
use Cadfael\Tests\Engine\Check\ColumnBuilder;
use Cadfael\Tests\Engine\Check\IndexBuilder;


class SaneInnoDbPrimaryKeyTest extends BaseTest
{
    protected $simplePrimaryKeyColumn;
    protected $mediumPrimaryKeyColumn;
    protected $complexPrimaryKeyColumn;
    protected $intColumn;
    protected $stringColumn;

    public function setUp(): void
    {
        $builder = new ColumnBuilder();
        $this->simplePrimaryKeyColumn = $builder->int(20)
            ->unsigned()
            ->primary()
            ->auto_increment()
            ->generate();

        $this->mediumPrimaryKeyColumn = $builder->varchar(8)
            ->primary()
            ->generate();

        $this->complexPrimaryKeyColumn = $builder->varchar(16)
            ->primary()
            ->generate();

        $this->intColumn= $builder->int(10)
            ->generate();

        $this->stringColumn= $builder->varchar(16)
            ->generate();
    }

    public function testSupports()
    {
        $check = new SaneInnoDbPrimaryKey();
        $this->assertTrue($check->supports($this->createTable()), "Ensure that InnoDb tables are supported.");
        $this->assertFalse($check->supports($this->createTable([ "ENGINE" => "MyISAM" ])), "Ensure that non-InnoDB tables are not supported.");
        $this->assertFalse($check->supports($this->createVirtualTable()), "Ensure that virtual tables are not supported.");
    }

    public function testRun()
    {
        $check = new SaneInnoDbPrimaryKey();

        $builder = new IndexBuilder();

        $this->assertNull($check->run($this->createTable()), "Ensure table without a PRIMARY KEY is ignored.");

        $tableWithNoOtherIndex = $this->createTable();
        $tableWithNoOtherIndex->setColumns(clone $this->simplePrimaryKeyColumn);
        $this->assertEquals(Report::STATUS_OK, $check->run($tableWithNoOtherIndex)->getStatus(), "Ensure table without other indexes is fine.");

        $smallIndexColumn = clone $this->intColumn;
        $tableWithSmallIndex = $this->createTable();
        $tableWithSmallIndex->setColumns(clone $this->simplePrimaryKeyColumn, $smallIndexColumn);
        $index = $builder->name('simple')->setColumn($smallIndexColumn)->generate();
        $tableWithSmallIndex->setIndexes($index);
        $this->assertEquals(Report::STATUS_OK, $check->run($tableWithSmallIndex)->getStatus(), "Ensure table with a small PRIMARY KEY and a small indexes is fine.");

        $largeIndexColumn = clone $this->stringColumn;
        $tableWithLargeIndex = $this->createTable();
        $tableWithLargeIndex->setColumns(clone $this->simplePrimaryKeyColumn, $largeIndexColumn);
        $index = $builder->name('large')->setColumn($largeIndexColumn)->generate();
        $tableWithLargeIndex->setIndexes($index);
        $this->assertEquals(Report::STATUS_OK, $check->run($tableWithLargeIndex)->getStatus(), "Ensure table with a small PRIMARY KEY and a large indexes is fine.");

        $smallIndexColumn = clone $this->intColumn;
        $tableWithMediumPrimaryAndSmallIndex = $this->createTable();
        $tableWithMediumPrimaryAndSmallIndex->setColumns(clone $this->mediumPrimaryKeyColumn, $smallIndexColumn);
        $index = $builder->name('simple')->setColumn($smallIndexColumn)->generate();
        $tableWithMediumPrimaryAndSmallIndex->setIndexes($index);
        $this->assertEquals(Report::STATUS_OK, $check->run($tableWithMediumPrimaryAndSmallIndex)->getStatus(), "Ensure table with a medium PRIMARY KEY and a small indexes is a warning.");

        $smallIndexColumn = clone $this->intColumn;
        $tableWithLargePrimaryAndSmallIndex = $this->createTable();
        $tableWithLargePrimaryAndSmallIndex->setColumns(clone $this->complexPrimaryKeyColumn, $smallIndexColumn);
        $index = $builder->name('simple')->setColumn($smallIndexColumn)->generate();
        $tableWithLargePrimaryAndSmallIndex->setIndexes($index);
        $this->assertEquals(Report::STATUS_WARNING, $check->run($tableWithLargePrimaryAndSmallIndex)->getStatus(), "Ensure table with a large PRIMARY KEY and a small indexes is a warning.");

        $largeIndexColumn = clone $this->stringColumn;
        $tableWithLargePrimaryAndLargeIndex = $this->createTable();
        $tableWithLargePrimaryAndLargeIndex->setColumns(clone $this->complexPrimaryKeyColumn, $largeIndexColumn);
        $index = $builder->name('large')->setColumn($largeIndexColumn)->generate();
        $tableWithLargePrimaryAndLargeIndex->setIndexes($index);
        $this->assertEquals(Report::STATUS_WARNING, $check->run($tableWithLargePrimaryAndLargeIndex)->getStatus(), "Ensure table with a large PRIMARY KEY and a large indexes is a warning.");
    }
}
