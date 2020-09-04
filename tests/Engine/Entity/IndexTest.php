<?php
declare(strict_types=1);

namespace Cadfael\Engine\Tests\Entity;

use Cadfael\Engine\Entity\Index;
use Cadfael\Engine\Entity\Table;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    protected Index $index;

    protected function setUp(): void
    {
        $this->index = new Index("MOCK_INDEX");
        $this->index->setTable(Table::createFromInformationSchema([
            "TABLE_CATALOG"     => "MOCK_CATALOG",
            "TABLE_SCHEMA"      => "MOCK_SCHEMA",
            "TABLE_NAME"        => "MOCK_TABLE",
            "TABLE_TYPE"        => "BASE TABLE",
            "ENGINE"            => "InnoDB",
            "VERSION"           => "10",
            "ROW_FORMAT"        => "Fixed",
            "TABLE_ROWS"        => 200,
            "AVG_ROW_LENGTH"    => 384,
            "DATA_LENGTH"       => 2311,
            "MAX_DATA_LENGTH"   => 16434816,
            "INDEX_LENGTH"      => 0,
            "DATA_FREE"         => 0,
            "AUTO_INCREMENT"    => null,
            "CREATE_TIME"       => "2020-05-30 11:29:56",
            "UPDATE_TIME"       => null,
            "CHECK_TIME"        => null,
            "TABLE_COLLATION"   => "utf8_general_ci",
            "CHECKSUM"          => null,
            "CREATE_OPTIONS"    => "",
            "TABLE_COMMENT"     => "",
        ]));
    }

    public function testIsVirtual()
    {
        $this->assertFalse($this->index->isVirtual());
    }

    public function testGetName()
    {
        $this->assertEquals("MOCK_INDEX", $this->index->getName());
    }

    public function test__toString()
    {
        $this->assertEquals("MOCK_TABLE.MOCK_INDEX", (string)$this->index);
    }
}
