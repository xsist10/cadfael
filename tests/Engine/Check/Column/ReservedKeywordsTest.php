<?php
declare(strict_types=1);

use Cadfael\Engine\Check\Column\ReservedKeywords;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\Check\ColumnBuilder;
use Cadfael\Tests\Engine\BaseTest;

class ReservedKeywordsTest extends BaseTest
{
    protected $nonReservedKeywordColumn;
    protected $reservedKeywordColumn;

    public function setUp(): void
    {
        $builder = new ColumnBuilder();
        $this->nonReservedKeywordColumn = $builder->name("nonReservedKeywordColumn")->generate();
        $this->nonReservedKeywordColumn->setTable($this->createTable());
        $this->reservedKeywordColumn = $builder->name(ReservedKeywords::RESERVED_KEYWORDS[array_rand(ReservedKeywords::RESERVED_KEYWORDS)])->generate();
        $this->reservedKeywordColumn->setTable($this->createTable());
    }

    public function testSupports()
    {
        $check = new ReservedKeywords();
        $this->assertTrue($check->supports($this->nonReservedKeywordColumn), "Ensure that the supports for $this->nonReservedKeywordColumn returns true.");
        $this->assertTrue($check->supports($this->reservedKeywordColumn), "Ensure that the supports for $this->reservedKeywordColumn returns true.");
    }

    public function testRun()
    {
        $check = new ReservedKeywords();
        $this->assertEquals(null, $check->run($this->nonReservedKeywordColumn), "Ensure that no report is returned for $this->nonReservedKeywordColumn.");
        $this->assertEquals(Report::STATUS_CONCERN, $check->run($this->reservedKeywordColumn)->getStatus(), "Ensure that the report status for $this->reservedKeywordColumn is CONCERN.");
    }
}
