<?php
declare(strict_types=1);

namespace Cadfael\Engine;

use Cadfael\Engine\Check\MySQL\Table\EmptyTable;
use Cadfael\Engine\Entity\MySQL\Schema;
use Cadfael\Engine\Entity\MySQL\Table;
use PHPUnit\Framework\TestCase;

class OrchestratorTest extends TestCase
{
    protected Orchestrator $orchestrator;
    protected Table $table;
    protected Schema $schema;
    protected Check $check;

    public function setUp(): void
    {
        $base = [
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
        ];
        $this->table = Table::createFromInformationSchema($base);
        $this->schema = new Schema('MOCK_SCHEMA');

        $this->check = new EmptyTable();
        $this->orchestrator = new Orchestrator();
        $this->orchestrator->addEntities($this->table, $this->schema);
        $this->orchestrator->addChecks($this->check);
    }

    public function testEntities()
    {
        $this->assertEquals(
            [ $this->table, $this->schema ],
            $this->orchestrator->getEntities(),
            "Ensure the entities accessor works as intended."
        );
    }

    public function testChecks()
    {
        $this->assertEquals(
            [ $this->check ],
            $this->orchestrator->getChecks(),
            "Ensure the checks accessor works as intended."
        );
    }

    public function testRun()
    {
        $this->assertEquals(
            [ new Report($this->check, $this->table, Report::STATUS_OK) ],
            $this->orchestrator->run(),
            "Ensure that running this will give us the single expected report."
        );
    }
}
