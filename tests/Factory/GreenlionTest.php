<?php /** @noinspection PhpUnreachableStatementInspection */

declare(strict_types=1);

namespace Cadfael\Tests\Factory;

use PHPSQLParser\PHPSQLParser;
use PHPUnit\Framework\TestCase;

class GreenlionTest extends TestCase
{
    private function getParsed($query)
    {
        $parser = new PHPSQLParser($query);
        return $parser->parsed;
    }

    public function testSimpleCreate()
    {
        $parsed = $this->getParsed("
            CREATE TABLE `table1` (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB;
        ");

        $primary_key_statement = $parsed["TABLE"]["create-def"]["sub_tree"][1];

        $this->assertEquals("primary-key", $primary_key_statement["expr_type"]);
        $this->assertEquals(
            ["expr_type" => "reserved", "base_expr" => "PRIMARY"],
            $primary_key_statement["sub_tree"][0]
        );
        $this->assertEquals(
            ["expr_type" => "reserved", "base_expr" => "KEY"],
            $primary_key_statement["sub_tree"][1]
        );
    }

    public function testCreateWithIndex()
    {
        $this->markTestSkipped('To be fixed in greenlion/PHP-SQL-Parser library.');

        $parsed = $this->getParsed("
            CREATE TABLE `table2` (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                INDEX idx_id (id),
                PRIMARY KEY (id)
            ) ENGINE=InnoDB;
        ");

        $primary_key_statement = $parsed["TABLE"]["create-def"]["sub_tree"][2];

        $this->assertEquals("primary-key", $primary_key_statement["expr_type"]);
        $this->assertEquals(
            ["expr_type" => "reserved", "base_expr" => "PRIMARY"],
            $primary_key_statement["sub_tree"][0]
        );
        $this->assertEquals(
            ["expr_type" => "reserved", "base_expr" => "KEY"],
            $primary_key_statement["sub_tree"][1]
        );
    }
}
