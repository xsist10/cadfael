<?php
declare(strict_types=1);

namespace Cadfael\Tests\Engine\Check\Column;

use Cadfael\Engine\Check\Column\LowCardinalityExpensiveStorage;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\BaseTest;
use Cadfael\Tests\Engine\Check\ColumnBuilder;

class LowCardinalityExpensiveStorageTest extends BaseTest
{
    protected $nonCharacterColumn;
    protected $characterColumnWithSmallTableAndHighCardinality;
    protected $characterColumnWithLargeTableAndHighCardinality;
    protected $characterColumnWithSmallTableAndLowCardinality;
    protected $characterColumnWithLargeTableAndLowCardinality;

    public function setUp(): void
    {
        $smallTable = $this->createTable([ 'TABLE_ROWS' => 10 ] );
        $largeTable = $this->createTable([ 'TABLE_ROWS' => 5000 ] );

        $builder = new ColumnBuilder();
        $this->nonCharacterColumn = $builder->name("nonCharacterColumn")
            ->int()
            ->generate();
        $this->nonCharacterColumn->setTable($this->createTable());

        $characterColumnWithHighCardinality = $builder->name("characterColumnWithHighCardinality")
            ->varchar(255)
            ->generate();
        $characterColumnWithHighCardinality->setCardinality(1000);

        $this->characterColumnWithSmallTableAndHighCardinality = clone $characterColumnWithHighCardinality;
        $this->characterColumnWithLargeTableAndHighCardinality = clone $characterColumnWithHighCardinality;

        $this->characterColumnWithSmallTableAndHighCardinality->setTable(clone $smallTable);
        $this->characterColumnWithLargeTableAndHighCardinality->setTable(clone $largeTable);

        $characterColumnWithLowCardinality = $builder->name("characterColumnWithLowCardinality")
            ->varchar(255)
            ->generate();
        $characterColumnWithLowCardinality->setCardinality(2);


        $this->characterColumnWithSmallTableAndLowCardinality = clone $characterColumnWithLowCardinality;
        $this->characterColumnWithLargeTableAndLowCardinality = clone $characterColumnWithLowCardinality;

        $this->characterColumnWithSmallTableAndLowCardinality->setTable(clone $smallTable);
        $this->characterColumnWithLargeTableAndLowCardinality->setTable(clone $largeTable);
    }

    public function testSupports()
    {
        $check = new LowCardinalityExpensiveStorage();

        $this->assertTrue($check->supports($this->nonCharacterColumn));
        $this->assertTrue($check->supports($this->characterColumnWithSmallTableAndHighCardinality));
        $this->assertTrue($check->supports($this->characterColumnWithLargeTableAndHighCardinality));
        $this->assertTrue($check->supports($this->characterColumnWithSmallTableAndLowCardinality));
        $this->assertTrue($check->supports($this->characterColumnWithLargeTableAndLowCardinality));
    }

    public function testRun()
    {
        $check = new LowCardinalityExpensiveStorage();
        $this->assertEquals(Report::STATUS_OK, $check->run($this->nonCharacterColumn)->getStatus());
        $this->assertEquals(Report::STATUS_OK, $check->run($this->characterColumnWithSmallTableAndHighCardinality)->getStatus());
        $this->assertEquals(Report::STATUS_OK, $check->run($this->characterColumnWithLargeTableAndHighCardinality)->getStatus());
        $this->assertEquals(Report::STATUS_OK, $check->run($this->characterColumnWithSmallTableAndLowCardinality)->getStatus());
        $this->assertEquals(Report::STATUS_CONCERN, $check->run($this->characterColumnWithLargeTableAndLowCardinality)->getStatus());
    }
}
