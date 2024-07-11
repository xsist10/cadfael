<?php
declare(strict_types=1);

namespace Cadfael\Tests\Engine\Entity;

use Cadfael\Engine\Entity\Column;
use Cadfael\Engine\Entity\Database;
use Cadfael\Engine\Entity\Schema;
use Cadfael\Engine\Entity\Table;
use Cadfael\Engine\Exception\QueryParseException;
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

    public function test__fetchColumnsModifiedByFunctions()
    {
        // No columns modified due to lack of WHERE statement
        $query = $this->createQuery("SELECT * FROM users", $this->schema);
        $this->assertEmpty($query->fetchColumnsModifiedByFunctions(), "No WHERE statement means no columns to check");

        $query = $this->createQuery("SELECT * FROM (users)", $this->schema);
        $this->assertEmpty($query->fetchColumnsModifiedByFunctions(), "No WHERE statement means no columns to check (with table expression)");

        $query = $this->createQuery("SELECT * FROM users WHERE id=?", $this->schema);
        $this->assertEmpty($query->fetchColumnsModifiedByFunctions(), "No modified columns");

        $query = $this->createQuery("SELECT * FROM test.users WHERE DATE_FORMAT(id)=?", $this->schema);
        $this->assertEquals([['table' => $this->table, 'column' => $this->column]], $query->fetchColumnsModifiedByFunctions(), "DATE_FORMAT modification on first column using relative column name.");

        $query = $this->createQuery("SELECT * FROM test.users WHERE DATE_FORMAT(CONCATENATE(id, 'moo'))=?", $this->schema);
        $this->assertEquals([['table' => $this->table, 'column' => $this->column]], $query->fetchColumnsModifiedByFunctions(), "DATE_FORMAT modification with nexted CONCATENATE on first column using relative column name.");

        $query = $this->createQuery("SELECT * FROM test.users WHERE id=? OR DATE_FORMAT(id)=?", $this->schema);
        $this->assertEquals([['table' => $this->table, 'column' => $this->column]], $query->fetchColumnsModifiedByFunctions(), "DATE_FORMAT modification on second column using relative column name.");

        $query = $this->createQuery("SELECT * FROM test.users WHERE DATE_FORMAT(users.id)=?", $this->schema);
        $this->assertEquals([['table' => $this->table, 'column' => $this->column]], $query->fetchColumnsModifiedByFunctions(), "DATE_FORMAT modification on first column using absolute column name.");

        $query = $this->createQuery("SELECT * FROM invalid_table WHERE DATE_FORMAT(id)=?", $this->schema);
        $this->assertEmpty($query->fetchColumnsModifiedByFunctions(), "DATE_FORMAT modification on first column for invalid table.");

        $query = $this->createQuery("SELECT * FROM test.users WHERE DATE_FORMAT(?)=?", $this->schema);
        $this->assertEmpty($query->fetchColumnsModifiedByFunctions(), "DATE_FORMAT modification on literal.");

        $query = $this->createQuery("SELECT b.id FROM test.users AS b WHERE DATE_FORMAT(SELECT id FROM users WHERE DATE_FORMAT(?)=?)=?", $this->schema);
        $this->assertEmpty($query->fetchColumnsModifiedByFunctions(), "DATE_FORMAT modification on literal.");
    }



    public function test__fetchColumnsModifiedByFunctionsException()
    {
        $this->expectException(QueryParseException::class);
        $query = $this->createQuery("SET VARIABLE a=b", $this->schema);
        $this->assertEmpty($query->fetchColumnsModifiedByFunctions(), "DATE_FORMAT modification on literal.");
    }


    public function test__getTableNamesInQuery()
    {
        $query = $this->createQuery(
            "SELECT
            order_month,
            order_day,
            COUNT(DISTINCT order_id) AS num_orders,
            COUNT(book_id) AS num_books,
            SUM(price) AS total_price,
            SUM(COUNT(book_id)) OVER (
              PARTITION BY order_month
              ORDER BY order_day
            ) AS running_total_num_books,
            LAG(COUNT(book_id), 7) OVER (ORDER BY order_day) AS prev_books
            FROM (
              SELECT
              DATE_FORMAT(co.order_date, '%Y-%m') AS order_month,
              DATE_FORMAT(co.order_date, '%Y-%m-%d') AS order_day,
              co.order_id,
              ol.book_id,
              ol.price
              FROM cust_order co
              INNER JOIN order_line ol ON co.order_id = ol.order_id
            ) sub
            GROUP BY order_month, order_day
            ORDER BY order_day ASC;",
            $this->schema
        );
        $tables = $query->getTableNamesInQuery();
        $this->assertCount(2, $tables, "Found correct number of tables");

        $query = $this->createQuery(
            "SELECT
                DATE_FORMAT(co.order_date, '%Y-%m') AS order_month,
                DATE_FORMAT(co.order_date, '%Y-%m-%d') AS order_day,
                COUNT(DISTINCT co.order_id) AS num_orders,
                COUNT(ol.book_id) AS num_books,
                SUM(ol.price) AS total_price,
                SUM(COUNT(ol.book_id)) OVER (
                        PARTITION BY DATE_FORMAT(co.order_date, '%Y-%m')
                  ORDER BY DATE_FORMAT(co.order_date, '%Y-%m-%d')
                ) AS running_total_num_books
            FROM cust_order co
            INNER JOIN order_line ol ON co.order_id = ol.order_id
            GROUP BY
              DATE_FORMAT(co.order_date, '%Y-%m'),
              DATE_FORMAT(co.order_date, '%Y-%m-%d')
            ORDER BY co.order_date ASC",
            $this->schema
        );
        $tables = $query->getTableNamesInQuery();
        $this->assertCount(2, $tables, "Found correct number of tables");

        $query = $this->createQuery(
            "SELECT
            c.calendar_date,
            c.calendar_year,
            c.calendar_month,
            c.calendar_dayname,
            COUNT(DISTINCT sub.order_id) AS num_orders,
            COUNT(sub.book_id) AS num_books,
            SUM(sub.price) AS total_price,
            SUM(COUNT(sub.book_id)) OVER (
                    PARTITION BY c.calendar_year, c.calendar_month
              ORDER BY c.calendar_date
            ) AS running_total_num_books,
            LAG(COUNT(sub.book_id), 7) OVER (ORDER BY c.calendar_date) AS prev_books
            FROM calendar_days c
            LEFT JOIN (
                    SELECT
              DATE_FORMAT(co.order_date, '%Y-%m') AS order_month,
              DATE_FORMAT(co.order_date, '%Y-%m-%d') AS order_day,
              co.order_id,
              ol.book_id,
              ol.price
              FROM cust_order co
              INNER JOIN order_line ol ON co.order_id = ol.order_id
            ) sub ON c.calendar_date = sub.order_day
            GROUP BY c.calendar_date, c.calendar_year, c.calendar_month, c.calendar_dayname
            ORDER BY c.calendar_date ASC",
            $this->schema
        );
        $tables = $query->getTableNamesInQuery();
        $this->assertCount(3, $tables, "Found correct number of tables");


        $query = $this->createQuery(
            "SELECT MIN(place_id) AS place_id,
                   name,
                   administration,
                   country,
                   MAX(relevance) AS relevance
            FROM (
                SELECT *,
                       (rescale(population, mn_pop, mx_pop)              * 2.4) +
                       (rescale(name_relevance, mn_plre, mx_plre)        * 0.0) +
                       (rescale(distance, mx_dist, mn_dist)              * 2.2) +  -- inverted
                       if(t.country_id = (
                       SELECT country_id FROM country_names
                           WHERE name = 'Germany'
                           LIMIT 1
                       ), 0.6, 0)
                           / 4
                           AS relevance
                FROM (
                    SELECT resl.*,
                           MIN(aggr.population)             AS mn_pop,
                           MAX(aggr.population)             AS mx_pop,
                           MIN(aggr.name_relevance)         AS mn_plre,
                           MAX(aggr.name_relevance)         AS mx_plre,
                           MIN(aggr.distance)               AS mn_dist,
                           MAX(aggr.distance)               AS mx_dist
                    FROM (
                        SELECT p.population,
                               ST_DISTANCE_SPHERE(position, ST_POINTFROMTEXT(ST_ASTEXT(POINT(7.4653, 51.5136)), 4326)) AS distance,
                               MATCH(pn.name) AGAINST('+dor*' IN BOOLEAN MODE)     AS name_relevance
                        FROM places p
                        JOIN place_names pn ON p.id = pn.place_id
                        JOIN admin_names an ON p.admin_id = an.admin_id
                        JOIN country_names cn ON p.country_id = cn.country_id
                        JOIN languages l ON pn.language_id = l.id AND an.language_id = l.id AND cn.language_id = l.id
                        WHERE l.code_3 = 'ENG' AND MATCH(pn.name) AGAINST('+dor*' IN BOOLEAN MODE)
                        LIMIT 200
                    ) aggr
                    JOIN (
                        SELECT p.id AS place_id,
                               pn.name AS name,
                               an.name AS administration,
                               an.abbr AS admin_abbr,
                               cn.name AS country,
                               p.population AS population,
                               p.country_id AS country_id,
                               ST_DISTANCE_SPHERE(position, ST_POINTFROMTEXT(ST_ASTEXT(POINT(7.4653, 51.5136)), 4326)) AS distance,
                               MATCH(pn.name) AGAINST('+rom*' IN BOOLEAN MODE)     AS name_relevance
                        FROM places p
                        JOIN place_names pn ON p.id = pn.place_id
                        JOIN admin_names an ON p.admin_id = an.admin_id
                        JOIN country_names cn ON p.country_id = cn.country_id
                        JOIN languages l ON pn.language_id = l.id AND an.language_id = l.id AND cn.language_id = l.id
                        WHERE l.code_3 = 'ENG' AND MATCH(pn.name) AGAINST('+dor*' IN BOOLEAN MODE)
                        LIMIT 200
                    ) resl
                    GROUP BY place_id, resl.name, resl.administration, resl.admin_abbr, resl.country
                ) t
            ) t2
            WHERE place_id is not null
            GROUP BY country, administration, admin_abbr, name
            ORDER BY relevance DESC;",
            $this->schema
        );
        $tables = $query->getTableNamesInQuery();
        $this->assertCount(5, $tables, "Found correct number of tables");
    }
}
