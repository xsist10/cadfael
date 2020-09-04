<?php
declare(strict_types=1);

use Cadfael\Engine\Check\Table\MustHavePrimaryKey;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\Check\BaseTest;
use Cadfael\Tests\Engine\Check\ColumnBuilder;

class MustHavePrimaryKeyTest extends BaseTest
{
    protected $primaryKeyColumn;
    protected $nonPrimaryKeyColumn;

    public function setUp(): void
    {
        $builder = new ColumnBuilder();
        $this->primaryKeyColumn = $builder->int(20)
            ->unsigned()
            ->primary()
            ->auto_increment()
            ->generate();

        $this->nonPrimaryKeyColumn = $builder->int(20)
            ->unsigned()
            ->generate();
    }

    public function testSupports()
    {
        $check = new MustHavePrimaryKey();
        $this->assertTrue($check->supports($this->createTable()), "Ensure that tables are supported.");
        $this->assertFalse($check->supports($this->createVirtualTable()), "Ensure that virtual tables are not supported.");
    }

    public function testRun()
    {
        $check = new MustHavePrimaryKey();

        $nonPrimaryTable = $this->createTable();
        $nonPrimaryTable->setColumns($this->nonPrimaryKeyColumn);

        $this->assertEquals(Report::STATUS_CRITICAL, $check->run($nonPrimaryTable)->getStatus(), "Ensure table without PRIMARY KEY is marked as CRITICAL.");

        $primaryTable = $this->createTable();
        $primaryTable->setColumns($this->primaryKeyColumn);

        $this->assertEquals(Report::STATUS_OK, $check->run($primaryTable)->getStatus(), "Ensure table without PRIMARY KEY is marked as OK.");
    }
}
