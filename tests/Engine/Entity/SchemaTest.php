<?php
declare(strict_types=1);

namespace Cadfael\Tests\Engine\Entity;

use Cadfael\Engine\Entity\Database;
use Cadfael\Engine\Entity\Query;
use Cadfael\Engine\Entity\Schema;
use Cadfael\Engine\Entity\Table;
use Cadfael\Tests\Engine\BaseTest;

class SchemaTest extends BaseTest
{
    protected Database $database;
    protected Schema $schema;
    protected Table $table;

    const VERSION = '5.7';
    const VARIABLES = [
        'version' => self::VERSION
    ];

    protected function setUp(): void
    {
        $this->database = new Database(null);
        $this->database->setVariables(self::VARIABLES);
        $this->schema = new Schema('information_schema');
        $this->schema->setDatabase($this->database);

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

        $this->schema->setTables($this->table);
    }

    public function test__getName()
    {
        $this->assertEquals($this->schema->getName(), "information_schema", "Verify that the schema name is returned from getName().");
    }

    public function test__toString()
    {
        $this->assertEquals((string)$this->schema, "information_schema", "Verify that the schema name is returned from __toString().");
    }

    public function test__isVirtual()
    {
        $this->assertFalse($this->schema->isVirtual(), "Verify that the schema is correctly identified as not virtual.");;
    }

    public function test__setTables()
    {
        $this->assertEquals($this->schema, $this->table->getSchema(), "Ensure that the setTables() function back-populates the schema into the table instance too.");
    }

    public function test__addQuery()
    {
        $query = new Query("SELECT * FROM test");
        $this->schema->addQuery($query);
        $this->assertEquals($this->schema, $query->getSchema(), "Ensure that the addQuery() function back-populates the schema into the query instance too.");
    }
}
