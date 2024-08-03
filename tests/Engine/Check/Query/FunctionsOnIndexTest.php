<?php

namespace Cadfael\Tests\Engine\Check\Query;

use Cadfael\Engine\Check\Query\FunctionsOnIndex;
use Cadfael\Engine\Entity\Column;
use Cadfael\Engine\Entity\Database;
use Cadfael\Engine\Entity\Query;
use Cadfael\Engine\Entity\Schema;
use Cadfael\Engine\Entity\Table;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\BaseTest;
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
    }

    public function testPass()
    {
        $query = $this->createQuery(
            "
                SELECT u.*
                FROM `test` . `users` AS u
                WHERE u.id = ?",
            $this->schema
        );

        $check = new FunctionsOnIndex();
        $this->assertTrue($check->supports($query));

        $report = $check->run($query);
        $this->assertEquals(
            Report::STATUS_OK,
            $report->getStatus(),
            "Query should identify modified INDEX column in WHERE statement."
        );
    }

    public function testFailWhereFunction()
    {
        $query = $this->createQuery(
            "
                SELECT u.*
                FROM `test` . `users` AS u
                JOIN test.posts AS p ON (p.author_id = `u` . `id`)
                JOIN test.`comments` ON (`comments`.author_id = `u` . `id`)
                WHERE DATE ( u.`joined` ) = ?",
            $this->schema
        );

        $check = new FunctionsOnIndex();
        $this->assertTrue($check->supports($query));

        $report = $check->run($query);
        $this->assertEquals(
            Report::STATUS_WARNING,
            $report->getStatus(),
            "Query should identify modified INDEX column in WHERE statement."
        );
    }

    public function testFailWhereOperator()
    {
        $query = $this->createQuery(
            "
                SELECT u.*
                FROM `test` . `users` AS u
                JOIN test.posts AS p ON (p.author_id = `u` . `id`)
                JOIN test.`comments` ON (`comments`.author_id = `u` . `id`)
                WHERE DATE_FORMAT(p.posted, '%Y-%m-%d') > u.joined + INTERVAL 1 DAY",
            $this->schema
        );

        $check = new FunctionsOnIndex();
        $this->assertTrue($check->supports($query));

        $report = $check->run($query);
        $this->assertEquals(
            Report::STATUS_WARNING,
            $report->getStatus(),
            "Query should identify modified INDEX column in WHERE statement."
        );
    }

    public function testFailJoinFunction()
    {
        $query = $this->createQuery(
            "
                SELECT u.*
                FROM `test` . `users` AS u
                JOIN test.posts AS p ON (p.author_id = `u` . `id` AND DATE ( u.`joined` ) = ?)
                JOIN test.`comments` ON (`comments`.author_id = `u` . `id`)
                WHERE u.id = ?",
            $this->schema
        );

        $check = new FunctionsOnIndex();
        $this->assertTrue($check->supports($query));

        $report = $check->run($query);
        $this->assertEquals(
            Report::STATUS_WARNING,
            $report->getStatus(),
            "Query should identify modified INDEX column in WHERE statement."
        );
    }

    public function testFailJoinOperator()
    {
        $query = $this->createQuery(
            "
                SELECT u.*
                FROM `test` . `users` AS u
                JOIN test.posts AS p ON (p.author_id = `u` . `id` AND DATE_FORMAT(p.posted, '%Y-%m-%d') > u.joined + INTERVAL 1 DAY)
                JOIN test.`comments` ON (`comments`.author_id = `u` . `id`)
                WHERE u.id = 10",
            $this->schema
        );

        $check = new FunctionsOnIndex();
        $this->assertTrue($check->supports($query));

        $report = $check->run($query);
        $this->assertEquals(
            Report::STATUS_WARNING,
            $report->getStatus(),
            "Query should identify modified INDEX column in WHERE statement."
        );
    }
}
