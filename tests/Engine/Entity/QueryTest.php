<?php
declare(strict_types=1);

namespace Cadfael\Tests\Engine\Entity;

use Cadfael\Engine\Entity\Column;
use Cadfael\Engine\Entity\Database;
use Cadfael\Engine\Entity\Query;
use Cadfael\Engine\Entity\Schema;
use Cadfael\Engine\Entity\Table;
use Cadfael\Tests\Engine\BaseTest;
use Cadfael\Tests\Engine\Check\ColumnBuilder;

class QueryTest extends BaseTest
{
    protected Column $column;
    protected Table $table;
    protected Database $database;
    protected Schema $schema;

    protected function setUp(): void
    {
        $this->table = $this->createTable(['TABLE_NAME' => 'users']);
        $builder = new ColumnBuilder();
        $this->column = $builder->name("id")
            ->int()
            ->generate();
        $this->table->setColumns($this->column);

        $this->schema = $this->createSchema();
        $this->schema->setTables($this->table);
        $this->database = $this->createDatabase();
        $this->database->setSchemas($this->schema);
    }

    public function createQuery($digest)
    {
        $query = new Query($digest);
        $query->linkTablesToQuery($this->schema, $this->database);
        return $query;
    }
    public function test__fetchColumnsModifiedByFunctions()
    {
        $query = $this->createQuery("SELECT * FROM users");
        $this->assertEmpty($query->fetchColumnsModifiedByFunctions());

        $query = $this->createQuery("SELECT * FROM users WHERE id=1");
        $this->assertEmpty($query->fetchColumnsModifiedByFunctions());

        $query = $this->createQuery("SELECT * FROM test.users WHERE DATE_FORMAT(id)=1");
        $this->assertEquals([['table' => $this->table, 'column' => $this->column]], $query->fetchColumnsModifiedByFunctions());

        $query = $this->createQuery("SELECT * FROM test.users WHERE DATE_FORMAT(users.id)=1");
        $this->assertEquals([['table' => $this->table, 'column' => $this->column]], $query->fetchColumnsModifiedByFunctions());
    }
}
