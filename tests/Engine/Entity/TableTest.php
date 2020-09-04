<?php
/**
 * Created by PhpStorm.
 * User: tshone
 * Date: 30-5-20
 * Time: 13:24
 */

use Cadfael\Engine\Entity\Table;
use PHPUnit\Framework\TestCase;

class TableTest extends TestCase
{
    protected $table;

    protected function setUp(): void
    {
        $this->table = Table::createFromInformationSchema([
            "TABLE_CATALOG"     => "def",
            "TABLE_SCHEMA"      => "information_schema",
            "TABLE_NAME"        => "CHARACTER_SETS",
            "TABLE_TYPE"        => "SYSTEM VIEW",
            "ENGINE"            => "MEMORY",
            "VERSION"           => "10",
            "ROW_FORMAT"        => "Fixed",
            "TABLE_ROWS"        => null,
            "AVG_ROW_LENGTH"    => 384,
            "DATA_LENGTH"       => 0,
            "MAX_DATA_LENGTH"   => 16434816,
            "INDEX_LENGTH"      => 0,
            "DATA_FREE"         => 0,
            "AUTO_INCREMENT"    => null,
            "CREATE_TIME"       => "2020-05-30 11:29:56",
            "UPDATE_TIME"       => null,
            "CHECK_TIME"        => null,
            "TABLE_COLLATION"   => "utf8_general_ci",
            "CHECKSUM"          => null,
            "CREATE_OPTIONS"    => "max_rows=43690",
            "TABLE_COMMENT"     => "",
        ]);
    }

    public function test__getName()
    {
        $this->assertEquals($this->table->getName(), "CHARACTER_SETS", "Verify that the table name is returned from getName().");
    }

    public function test__toString()
    {
        $this->assertEquals((string)$this->table, "CHARACTER_SETS", "Verify that the schema and table name is returned from __toString().");
    }

    public function test__isVirtual()
    {
        $this->assertTrue($this->table->isVirtual(), "Verify that the table is correctly identified as virtual.");

        // Verify that an shell table is considered virtual by default
        $table = new Table("MOCK_SCHEMA", "MOCK_TABLE");
        $this->assertTrue($table->isVirtual());
    }

}
