<?php
declare(strict_types=1);

use Cadfael\Engine\Entity\MySQL\Table;
use Cadfael\Engine\Entity\MySQL\Column;
use PHPUnit\Framework\TestCase;
use Cadfael\Engine\Exception\UnknownColumnType;

class ColumnTest extends TestCase
{
    protected $table;
    protected $integerColumn;
    protected $stringColumn;
    protected $invalidColumn;

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

        $this->integerColumn = Column::createFromInformationSchema([
            "TABLE_NAME"                => "CHARACTER_SETS",
            "COLUMN_NAME"               => "id",
            "ORDINAL_POSITION"          => 1,
            "COLUMN_DEFAULT"            => "0",
            "IS_NULLABLE"               => "NO",
            "DATA_TYPE"                 => "int",
            "CHARACTER_MAXIMUM_LENGTH"  => NULL,
            "CHARACTER_OCTET_LENGTH"    => NULL,
            "NUMERIC_PRECISION"         => 10,
            "NUMERIC_SCALE"             => 0,
            "DATETIME_PRECISION"        => NULL,
            "CHARACTER_SET_NAME"        => NULL,
            "COLLATION_NAME"            => NULL,
            "COLUMN_TYPE"               => "int(20) unsigned",
            "COLUMN_KEY"                => "PRI",
            "EXTRA"                     => "auto_increment",
            "PRIVILEGES"                => "select",
            "COLUMN_COMMENT"            => "",
            "GENERATION_EXPRESSION"     => "",
        ]);

        $this->stringColumn = Column::createFromInformationSchema([
            "TABLE_NAME"                => "CHARACTER_SETS",
            "COLUMN_NAME"               => "name",
            "ORDINAL_POSITION"          => 2,
            "COLUMN_DEFAULT"            => "",
            "IS_NULLABLE"               => "NO",
            "DATA_TYPE"                 => "varchar",
            "CHARACTER_MAXIMUM_LENGTH"  => 30,
            "CHARACTER_OCTET_LENGTH"    => 96,
            "NUMERIC_PRECISION"         => NULL,
            "NUMERIC_SCALE"             => NULL,
            "DATETIME_PRECISION"        => NULL,
            "CHARACTER_SET_NAME"        => "utf8",
            "COLLATION_NAME"            => "utf8_general_ci",
            "COLUMN_TYPE"               => "varchar(30)",
            "COLUMN_KEY"                => "",
            "EXTRA"                     => "",
            "PRIVILEGES"                => "select",
            "COLUMN_COMMENT"            => "",
            "GENERATION_EXPRESSION"     => "derived",
        ]);

        $this->invalidColumn = Column::createFromInformationSchema([
            "TABLE_NAME"                => "CHARACTER_SETS",
            "COLUMN_NAME"               => "invalid",
            "ORDINAL_POSITION"          => 3,
            "COLUMN_DEFAULT"            => "",
            "IS_NULLABLE"               => "NO",
            "DATA_TYPE"                 => "invalid_type",
            "CHARACTER_MAXIMUM_LENGTH"  => NULL,
            "CHARACTER_OCTET_LENGTH"    => NULL,
            "NUMERIC_PRECISION"         => NULL,
            "NUMERIC_SCALE"             => NULL,
            "DATETIME_PRECISION"        => NULL,
            "CHARACTER_SET_NAME"        => NULL,
            "COLLATION_NAME"            => NULL,
            "COLUMN_TYPE"               => "invalid_type",
            "COLUMN_KEY"                => "",
            "EXTRA"                     => "",
            "PRIVILEGES"                => "",
            "COLUMN_COMMENT"            => "",
            "GENERATION_EXPRESSION"     => "",
        ]);

        $this->table->setColumns($this->integerColumn, $this->stringColumn);
    }

    public function test__getName()
    {
        $this->assertEquals($this->integerColumn->getName(), "id", "Verify that the column name is returned from getName().");
        $this->assertEquals($this->stringColumn->getName(), "name", "Verify that the column name is returned from getName().");
    }

    public function test__toString()
    {
        $this->assertEquals((string)$this->integerColumn, "CHARACTER_SETS.id", "Verify that the schema, table and column name is returned from __toString().");
        $this->assertEquals((string)$this->stringColumn, "CHARACTER_SETS.name", "Verify that the schema, table and column name is returned from __toString().");
    }

    public function test__isVirtual()
    {
        $this->assertFalse($this->integerColumn->isVirtual(), "Verify that the column is correctly identified as not virtual.");
        $this->assertTrue($this->stringColumn->isVirtual(), "Verify that the column is correctly identified as virtual.");
    }

    public function test__isPartOfPrimaryKey()
    {
        $this->assertTrue($this->integerColumn->isPartOfPrimaryKey(), "Verify if the column is part of a PRIMARY KEY or not.");
        $this->assertFalse($this->stringColumn->isPartOfPrimaryKey(), "Verify if the column is part of a PRIMARY KEY or not.");
    }

    public function test__isSigned()
    {
        $this->assertFalse($this->integerColumn->isSigned(), "Verify if the column is signed or not.");
        $this->assertFalse($this->stringColumn->isSigned(), "Verify if the column is signed or not.");
    }

    public function test__isAutoIncrementing()
    {
        $this->assertTrue($this->integerColumn->isAutoIncrementing(), "Verify if the column is auto-incrementing or not.");
        $this->assertFalse($this->stringColumn->isAutoIncrementing(), "Verify if the column is auto-incrementing or not.");
    }

    public function test__isInteger()
    {
        $this->assertTrue($this->integerColumn->isInteger(), "Verify if the column is an integer type or not.");
        $this->assertFalse($this->stringColumn->isInteger(), "Verify if the column is an integer type or not.");
    }

    public function test__isNumeric()
    {
        $this->assertTrue($this->integerColumn->isNumeric(), "Verify if the column is a number type or not.");
        $this->assertFalse($this->stringColumn->isNumeric(), "Verify if the column is a number type or not.");
    }

    public function test__getStorageByteSize()
    {
        $this->assertEquals(4, $this->integerColumn->getStorageByteSize(), "Return the storage size in bytes for the field.");
        $this->assertEquals(31, $this->stringColumn->getStorageByteSize(), "Return the storage size in bytes for the field.");
    }

    public function test__invalidColumnCalling__getStorageByteSize()
    {
        $this->expectException(UnknownColumnType::class);
        $this->invalidColumn->getStorageByteSize();
    }
}
