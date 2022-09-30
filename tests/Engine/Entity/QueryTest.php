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

    public function test__getTableNamesInQuery()
    {
        $query = new Query(
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
            ORDER BY order_day ASC;"
        );
        $tables = $query->getTableNamesInQuery();
        $this->assertCount(2, $tables, "Found correct number of tables");

        $query = new Query(
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
            ORDER BY co.order_date ASC"
        );
        $tables = $query->getTableNamesInQuery();
        $this->assertCount(2, $tables, "Found correct number of tables");

        $query = new Query(
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
            ORDER BY c.calendar_date ASC"
        );
        $tables = $query->getTableNamesInQuery();
        $this->assertCount(3, $tables, "Found correct number of tables");


        $query = new Query(
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
            ORDER BY relevance DESC;"
        );
        $tables = $query->getTableNamesInQuery();
        $this->assertCount(5, $tables, "Found correct number of tables");
    }
}
