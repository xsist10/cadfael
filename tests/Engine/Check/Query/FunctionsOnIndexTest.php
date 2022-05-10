<?php

namespace Cadfael\Tests\Engine\Check\Query;

use Cadfael\Engine\Check\Query\FunctionsOnIndex;
use Cadfael\Engine\Entity\Column;
use Cadfael\Engine\Entity\Database;
use Cadfael\Engine\Entity\Query;
use Cadfael\Engine\Entity\Schema;
use Cadfael\Engine\Entity\Table;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\Check\BaseTest;
use Cadfael\Tests\Engine\Check\IndexBuilder;

class FunctionsOnIndexTest extends BaseTest
{
    protected Database $database;
    protected Schema $schema;
    protected Query $query;

    protected function setUp(): void
    {
        $this->database = $this->createDatabase();
        $this->schema = $this->createSchema();
        $userTable = new Table("users");
        $joinedColumn = new Column("joined");
        $userTable->setColumns(new Column("id"), $joinedColumn);

        $builder = new IndexBuilder();
        $userTable->setIndexes($builder->name('joined')->setColumn($joinedColumn)->generate());

        $postTable = new Table("posts");
        $postTable->setColumns(new Column("posted"));

        $this->schema->setTables(
            $userTable,
            $postTable,
            new Table("comments"),
        );
        $this->database->setSchemas($this->schema);
        $this->query = new Query(
            "
                SELECT u.*
                FROM `test` . `users` AS u
                JOIN test.posts AS p ON (p.author_id = `u` . `id`)
                JOIN test.`comments` ON (`comments`.author_id = `u` . `id`)
                WHERE DATE ( u.`joined` ) = ? AND ( DATE_FORMAT(p.posted, '%Y-%m-%d') > u.joined + INTERVAL 1 DAY)"
        );

        $this->query->linkTablesToQuery($this->schema, $this->database);
    }

    public function test__moo()
    {
        $check = new FunctionsOnIndex();
        $report = $check->run($this->query);
        $this->assertEquals(Report::STATUS_WARNING, $report->getStatus(), "Query should identify modified INDEX column in WHERE statement.");
    }
}
