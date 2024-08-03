<?php

declare(strict_types=1);


namespace Cadfael\Tests\Builder;

use Cadfael\Builder\Builder;
use Cadfael\Engine\Check\Column\SaneAutoIncrement;
use Cadfael\Engine\Check\Table\MustHavePrimaryKey;
use Cadfael\Engine\Exception\ExistingColumn;
use Cadfael\Engine\Exception\InvalidColumn;
use Cadfael\Engine\Exception\QueryParseException;
use Cadfael\Engine\Report;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerAwareInterface;
use SqlFtw\Parser\InvalidTokenException;

class BuilderTest extends TestCase
{
    protected Builder $builder;

    protected function setUp(): void
    {
        $this->builder = new Builder("8.0");
    }

    public function testCreateTableWithoutSchemaHasDefault()
    {
        $schemas = $this->builder->processIntoSchemas("
            CREATE TABLE `example1` (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
            );
        ");
        $this->assertCount(1, $schemas, "Ensure only one schema was created.");
        $this->assertEquals(Builder::DEFAULT_SCHEMA, $schemas[0]->getName(), "Ensure our schema has the default name.");

        $schema = $schemas[0];
        $tables = $schema->getTables();
        $columns = $tables[0]->getColumns();
        $this->assertCount(1, $tables, "Ensure the schema has one table.");
        $this->assertCount(1, $columns, "Ensure the tables has one column.");
    }

    public function testCreateTableWithSchemaHasCorrectName()
    {
        $schemas = $this->builder->processIntoSchemas("
            USE example_db;
            CREATE TABLE `example1` (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
            );
        ");

        $this->assertCount(1, $schemas, "Ensure only one schema was created.");
        $this->assertEquals('example_db', $schemas[0]->getName(), "Ensure our schema has the correct name.");
    }

    public function testCreateDropSchema()
    {
        $schemas = $this->builder->processIntoSchemas("
            CREATE DATABASE example_db;
            DROP DATABASE example_db;
        ");

        $this->assertCount(0, $schemas, "Ensure no schema was created.");
    }

    public function testCreateDropSchemaWithUse()
    {
        $schemas = $this->builder->processIntoSchemas("
            CREATE DATABASE example_db;
            USE example_db;
            DROP DATABASE example_db;
        ");

        $this->assertCount(0, $schemas, "Ensure no schema was created.");
    }

    public function testCreateDropSchemaWithTable()
    {
        $schemas = $this->builder->processIntoSchemas("
            CREATE DATABASE example_db;
            USE example_db;
            CREATE TABLE `example1` (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
            );
            DROP DATABASE example_db;
        ");

        $this->assertCount(0, $schemas, "Ensure no schema was created.");
    }

    public function testCreateDropTableWithExplicitSchema()
    {
        $schemas = $this->builder->processIntoSchemas("
            CREATE TABLE `example_db`.`example1` (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
            );
            DROP TABLE `example_db`.`example1`;
        ");

        $this->assertCount(1, $schemas, "Ensure a schema was created.");
        $this->assertCount(0, $schemas[0]->getTables(), "Ensure the schema has no tables.");
    }

    public function testCreateDropTableWithDefaultSchema()
    {
        $schemas = $this->builder->processIntoSchemas("
            CREATE TABLE `example1` (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
            );
            DROP TABLE `example1`;
        ");

        $this->assertCount(0, $schemas, "We don't create an empty default schema.");
    }

    public function testCreateDropSchemaWithUseAndCreateTable()
    {
        $schemas = $this->builder->processIntoSchemas("
            CREATE DATABASE example_db;
            USE example_db;
            DROP DATABASE example_db;
            
            CREATE TABLE `example1` (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
            );
        ");

        $this->assertCount(1, $schemas, "Ensure only one schema was created.");
        $this->assertEquals(Builder::DEFAULT_SCHEMA, $schemas[0]->getName(), "Ensure our schema has the correct name.");
    }

    public function testDropSchemaBeforeDropTable()
    {
        $schemas = $this->builder->processIntoSchemas("
            CREATE DATABASE example_db;
            USE example_db;
            CREATE TABLE `example1` (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
            );
            DROP DATABASE example_db;
            DROP TABLE example1;
        ");

        $this->assertCount(0, $schemas, "Ensure no schema remains.");
    }

    public function testCreateTableWithExplicitSchemaName()
    {
        $schemas = $this->builder->processIntoSchemas("
            CREATE TABLE `example_db`.`example1` (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
            );
        ");

        $this->assertCount(1, $schemas, "Ensure only one schema was created.");
        $this->assertEquals('example_db', $schemas[0]->getName(), "Ensure our schema has the correct name.");
    }

    public function testColumnExtractionCorrectness()
    {
        $schemas = $this->builder->processIntoSchemas("
            CREATE TABLE `test_table` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `created` DATETIME(6) NOT NULL,
                PRIMARY KEY (`id`)
            );
        ");


        $table = $schemas[0]->getTables()[0];
        $this->assertCount(3, $table->getColumns(), 'Ensure the correct number of columns were created.');
        $this->assertEquals('id', $table->getAutoIncrementColumn()->getName(), 'Ensure the ID column is correctly identified as the auto increment column.');
        $this->assertCount(1, $table->getPrimaryKeys(), 'Ensure we have one primary key for the table.');

        $information_schema = $table->getColumn('id')->information_schema;
        $this->assertNull($information_schema->character_maximum_length, 'Ensure our int length is correct.');
        $this->assertNull($information_schema->character_octet_length, 'Ensure our int octet length is correct.');
        $this->assertEquals(11, $information_schema->numeric_precision, 'Ensure our int numeric precision is correct.');

        $information_schema = $table->getColumn('name')->information_schema;
        $this->assertEquals(255, $information_schema->character_maximum_length, 'Ensure our varchar length is correct.');
        $this->assertEquals(1020, $information_schema->character_octet_length, 'Ensure our varchar octet length is correct.');

        $information_schema = $table->getColumn('created')->information_schema;
        $this->assertEquals(6, $information_schema->datetime_precision, 'Ensure our date time precision is correct.');
    }

    public function testPrimaryKeyDefinitionSeparateStatementBackticks()
    {
        $schemas = $this->builder->processIntoSchemas("
            DROP TABLE IF EXISTS `table_empty_in_tablespace`;
            CREATE TABLE `table_empty_in_tablespace` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                PRIMARY KEY (`id`)
            ) /*!50100 TABLESPACE `innodb_system` */ ENGINE=InnoDB;
        ");


        $table = $schemas[0]->getTables()[0];
        $this->assertCount(2, $table->getColumns(), 'Ensure the correct number of columns were created.');
        $this->assertEquals('id', $table->getAutoIncrementColumn()->getName(), 'Ensure the ID column is correctly identified as the auto increment column.');
        $this->assertCount(1, $table->getPrimaryKeys(), 'Ensure we have one primary key for the table.');

        $information_schema = $table->getColumn('id')->information_schema;
        $this->assertNull($information_schema->character_maximum_length, 'Ensure our int length is correct.');
        $this->assertNull($information_schema->character_octet_length, 'Ensure our int octet length is correct.');
        $this->assertEquals(11, $information_schema->numeric_precision, 'Ensure our int numeric precision is correct.');

        $information_schema = $table->getColumn('name')->information_schema;
        $this->assertEquals(255, $information_schema->character_maximum_length, 'Ensure our varchar length is correct.');
        $this->assertEquals(1020, $information_schema->character_octet_length, 'Ensure our varchar octet length is correct.');
    }

    public function testPrimaryKeyDefinitionInline()
    {
        $schemas = $this->builder->processIntoSchemas("
            CREATE TABLE `example1` (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
            );
        ");

        $table = $schemas[0]->getTables()[0];

        $this->assertCount(1, $table->getColumns(), 'Ensure the correct number of columns were created.');
        $this->assertEquals('id', $table->getAutoIncrementColumn()->getName(), 'Ensure the ID column is correctly identified as the auto increment column.');
        $this->assertCount(1, $table->getPrimaryKeys(), 'Ensure we have one primary key for the table.');
    }

    /**
     * For some reason a table description with indexes causes the SQL parser to behave differently
     */
    public function testPrimaryKeyDefinitionSeparateStatement()
    {
        $schemas = $this->builder->processIntoSchemas("
            CREATE TABLE `table_with_large_text_index` (
                id INT NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                INDEX idx_name (name),
                PRIMARY KEY (id)
            ) ENGINE=InnoDB;
        ");

        $table = $schemas[0]->getTables()[0];

        $this->assertEquals('id', $table->getAutoIncrementColumn()->getName(), 'Ensure the ID column is correctly identified as the auto increment column.');
        $this->assertCount(1, $table->getPrimaryKeys(), 'Ensure we have one primary key for the table.');

        $check = new MustHavePrimaryKey();
        $report = $check->run($table);
        $this->assertEquals(Report::STATUS_OK, $report->getStatus(), "Ensure our primary key check passes.");

        $check = new SaneAutoIncrement();
        $primary_column = $table->getPrimaryKeys()[0];
        $report = $check->run($primary_column);
        $this->assertEquals(Report::STATUS_WARNING, $report->getStatus(), "Ensure our sane primary key check fails.");
    }

    public function testIndexDefinitionStatement()
    {
        $schemas = $this->builder->processIntoSchemas("
            CREATE TABLE `example2` (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(64) NOT NULL,
                email VARCHAR(255) NOT NULL,
                PRIMARY KEY id (id),
                UNIQUE idx_email (email),
                INDEX idx_name_email (name, email)
            );
        ");

        $table = $schemas[0]->getTables()[0];
        $this->assertCount(3, $table->getColumns(), 'Ensure the correct number of columns were created.');
        $this->assertCount(3, $table->getIndexes(), 'Ensure we have detected the correct number of indexes');
        $this->assertTrue($table->getIndexes()[0]->isUnique(), 'Ensure we have a unique index for the primary key.');
        $this->assertTrue($table->getIndexes()[1]->isUnique(), 'Ensure we have a unique index for the unique key.');
    }

    public function testInlineIndexDefinitionStatement()
    {
        $schemas = $this->builder->processIntoSchemas("
            CREATE TABLE `example2` (
                id INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(64) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE
            );
        ");

        $table = $schemas[0]->getTables()[0];
        $this->assertCount(3, $table->getColumns(), 'Ensure the correct number of columns were created.');
        $this->assertCount(2, $table->getIndexes(), 'Ensure we have detected the correct number of indexes');
        $this->assertTrue($table->getIndexes()[0]->isUnique(), 'Ensure we have a unique index for the primary key.');
        $this->assertTrue($table->getIndexes()[1]->isUnique(), 'Ensure we have a unique index for the unique key.');
    }

    public function testIndexDefinitionStatementWithParts()
    {
        $schemas = $this->builder->processIntoSchemas("
            CREATE TABLE `example2` (
                id INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(64) NOT NULL,
                INDEX idx_name (name(20))
            );
        ");


        $table = $schemas[0]->getTables()[0];

        $indexes = $table->getIndexes();
        $this->assertCount(2, $indexes, 'Ensure we have detected the correct number of indexes');

        $statistics = $indexes[1]->getStatistics();
        $this->assertEquals(20, $statistics[0]->sub_part, 'Ensure we capture the index sub_part accurately.');
    }

    public function testIndexDefinitionStatementWithInvalidColumn()
    {
        $this->expectException(InvalidColumn::class);

        $schemas = $this->builder->processIntoSchemas("
            CREATE TABLE `example2` (
                id INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                INDEX idx_non_existent_column (non_existent_column)
            );
        ");
    }

    public function testPrimaryKeyDefinitionStatementWithInvalidColumn()
    {
        $this->expectException(InvalidColumn::class);

        $this->builder->processIntoSchemas("
            CREATE TABLE `example2` (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                PRIMARY KEY (non_existent_column)
            );
        ");
    }

    public function testCharacterSetNameCollationImplicit()
    {
        $schemas = $this->builder->processIntoSchemas("
            CREATE TABLE `example` (
                a VARCHAR(255) CHARACTER SET utf8mb4,
                b VARCHAR(255) CHARACTER SET latin1,
                c VARCHAR(255)
            );
        ");

        $table = $schemas[0]->getTables()[0];
        $columns = $table->getColumns();
        $this->assertNull($table->getAutoIncrementColumn(), 'Ensure there is no defined auto incrementing column.');
        $this->assertEquals("utf8mb4", $columns[0]->information_schema->character_set_name);
        $this->assertEquals("utf8mb4_0900_ai_ci", $columns[0]->information_schema->collation_name);
        $this->assertEquals("latin1", $columns[1]->information_schema->character_set_name);
        $this->assertEquals("latin1_swedish_ci", $columns[1]->information_schema->collation_name);
        $this->assertEquals("latin1", $columns[2]->information_schema->character_set_name);
        $this->assertEquals("latin1_swedish_ci", $columns[2]->information_schema->collation_name);
    }

    public function testCharacterSetNameExplicit()
    {
        $schemas = $this->builder->processIntoSchemas("
            CREATE TABLE `example` (
                a VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                b VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci,
                c VARCHAR(255),
                d INT
            ) CHARACTER SET latin1 COLLATE latin1_bin;
        ");

        $table = $schemas[0]->getTables()[0];
        $columns = $table->getColumns();
        $this->assertEquals("utf8mb4", $columns[0]->information_schema->character_set_name);
        $this->assertEquals("utf8mb4_unicode_ci", $columns[0]->information_schema->collation_name);
        $this->assertEquals("latin1", $columns[1]->information_schema->character_set_name);
        $this->assertEquals("latin1_swedish_ci", $columns[1]->information_schema->collation_name);
        $this->assertEquals("latin1", $columns[2]->information_schema->character_set_name);
        $this->assertEquals("latin1_bin", $columns[2]->information_schema->collation_name);
        $this->assertNull($columns[3]->information_schema->character_set_name);
        $this->assertNull($columns[3]->information_schema->collation_name);
    }

    public function testCharacterSetWithCollationInTable()
    {
        $schemas = $this->builder->processIntoSchemas("
            CREATE TABLE IF NOT EXISTS example (
                a VARCHAR(255) CHARSET ascii,
                b VARCHAR(255) CHARSET ascii COLLATE ascii_bin,
                c TEXT CHARACTER SET ascii,
                d VARCHAR(32) CHARACTER SET ascii
            );
        ");

        $table = $schemas[0]->getTables()[0];
        $columns = $table->getColumns();
        $this->assertEquals("ascii", $columns[0]->information_schema->character_set_name);
        $this->assertEquals("ascii_general_ci", $columns[0]->information_schema->collation_name);
        $this->assertEquals("ascii", $columns[1]->information_schema->character_set_name);
        $this->assertEquals("ascii_bin", $columns[1]->information_schema->collation_name);
        $this->assertEquals("ascii", $columns[2]->information_schema->character_set_name);
        $this->assertEquals("ascii_general_ci", $columns[2]->information_schema->collation_name);
        $this->assertEquals("ascii", $columns[3]->information_schema->character_set_name);
        $this->assertEquals("ascii_general_ci", $columns[3]->information_schema->collation_name);
    }

    public function testInvalidCharacterSetInColumn()
    {
        $this->expectException(InvalidTokenException::class);

        $this->builder->processIntoSchemas("
            CREATE TABLE `example` (
                a VARCHAR(255) CHARACTER SET invalid_character_set
            );
        ");
    }

    public function testInvalidCharacterSetInTable()
    {
        $this->expectException(InvalidTokenException::class);

        $this->builder->processIntoSchemas("
            CREATE TABLE `example` (
                a VARCHAR(255)
            ) CHARACTER SET invalid_character_set;
        ");
    }

    public function testInvalidCharacterSetWithCollationInTable()
    {
        $this->expectException(InvalidTokenException::class);

        $this->builder->processIntoSchemas("
            CREATE TABLE `example` (
                a VARCHAR(255)
            ) CHARACTER SET invalid_character_set COLLATE latin1_bin;
        ");
    }

    public function testTableOptions()
    {
        $schemas = $this->builder->processIntoSchemas("
            CREATE TABLE `example` (
                a VARCHAR(255)
            ) AUTO_INCREMENT=100 ENGINE=MyISAM CHARACTER SET latin1 COLLATE latin1_danish_ci;
        ");

        $table = $schemas[0]->getTables()[0];
        $this->assertEquals("latin1_danish_ci", $table->information_schema->table_collation);
        $this->assertEquals("MyISAM", $table->information_schema->engine);
        $this->assertEquals(100, $table->information_schema->auto_increment);
    }

    public function testSetOperationIgnore()
    {
        $schemas = $this->builder->processIntoSchemas("
            SET @total_tax = (SELECT SUM(tax) FROM taxable_transactions);
        ");

        $this->assertCount(0, $schemas, "No schemas should be generated for this.");
    }

    public function testCreateProcedureIgnore()
    {
        $schemas = $this->builder->processIntoSchemas("
            DROP TRIGGER trigger_name;
        ");

        $this->assertCount(0, $schemas, "No schemas should be generated for this.");
    }

    /**
     * @group alter
     */
    public function testAlterAddColumn()
    {
        $schemas = $this->builder->processIntoSchemas("
            CREATE TABLE example1 (a INT);
            ALTER TABLE example1
                ADD COLUMN b VARCHAR(32),
                ADD INDEX idx_b (b);
        ");

        $this->assertCount(1, $schemas, "One default schemas should be generated for this.");
        $tables = $schemas[0]->getTables();
        $this->assertCount(1, $tables, "One table should exist int the table.");
        $columns = $tables[0]->getColumns();
        $this->assertCount(2, $columns, "Two columns should be generated.");
        $this->assertCount(1, $tables[0]->getIndexes(), "One index should have been created.");
    }

    public function testMultiplePrimaryKeyColumns()
    {
        $schemas = $this->builder->processIntoSchemas("
            CREATE TABLE example1 (
              a INT NOT NULL,
              b BIGINT NOT NULL,
              PRIMARY KEY (a, b)
            );
        ");

        $table = $schemas[0]->getTables()[0];

        $this->assertCount(2, $table->getColumns());
        $this->assertCount(1, $table->getIndexes());
        $this->assertCount(2, $table->getPrimaryKeys());
    }

    public function testMultipleTableDeclarations()
    {
        $schemas = $this->builder->processIntoSchemas("
            CREATE TABLE example1 (
              a INT NOT NULL,
              b BIGINT NOT NULL,
              PRIMARY KEY (a, b)
            );

            -- This should be ignored
            CREATE TABLE example1 (
              a INT NOT NULL
            );
        ");

        $table = $schemas[0]->getTables()[0];

        $this->assertCount(2, $table->getColumns());
        $this->assertCount(1, $table->getIndexes());
        $this->assertCount(2, $table->getPrimaryKeys());
    }


    public function testUnexpectedQueries()
    {
        $this->expectException(QueryParseException::class);

        $this->builder->processIntoSchemas("
            SHOW CREATE TABLE random_table;
        ");
    }

    public function testAlterAddColumnConflict()
    {
        $this->expectException(ExistingColumn::class);

        $this->builder->processIntoSchemas("
            CREATE TABLE example1 (
              a INT NOT NULL
            );

            ALTER TABLE example1 ADD COLUMN a INT NOT NULL;
        ");
    }

    public function testLargeSequence()
    {
        $schemas = $this->builder->processIntoSchemas("
            -- Create a schema to be dropped
            CREATE DATABASE IF NOT EXISTS to_be_dropped;
            USE to_be_dropped;
            
            DROP DATABASE test;
            CREATE DATABASE IF NOT EXISTS test;
    
            DROP TABLE IF EXISTS `test`.`table_with_large_text_index`;
            CREATE TABLE IF NOT EXISTS `test`.`table_with_large_text_index` (
                id INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL,
                address TEXT,
                postal_code VARCHAR(10) COMMENT 'This should cover most situations',
                last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_name_postal_code (name, postal_code),
                UNIQUE KEY (address),
                PRIMARY KEY (id)
            ) AUTO_INCREMENT=100 ENGINE=InnoDB;
            
            -- Drop the database before selecting a new one.
            -- TODO: Add a test to see what happens if I attempt to define a new table before changing schema. 
            DROP DATABASE to_be_dropped;
            USE test;
            
            INSERT INTO `table_with_large_test_index` (name) VALUES ('Testy McTest');

            CREATE PROCEDURE count (IN val INT, OUT col INT)
            BEGIN
                SELECT 1;
            END;

            CREATE FUNCTION count (val INT)
            RETURNS decimal
            BEGIN
                SELECT 1;
            END;

            DESCRIBE table_with_large_text_index;

            DROP TABLE IF EXISTS `table_empty`;
            CREATE TABLE `table_empty` (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB;
        ");

        $schema = $schemas[0];

        $this->assertEquals('test', $schema->getName(), 'Ensure the schema is correctly named.');
        $this->assertCount(2, $schema->getTables(), 'Ensure the correct number of tables was created.');
        $this->assertEquals('table_with_large_text_index', $schema->getTables()[0]->getName(), 'Ensure the first table is correctly named.');

        // Table creation tests
        $table1 = $schema->getTables()[0];
        $this->assertCount(5, $table1->getColumns(), 'Ensure the correct number of columns were created.');
        $this->assertEquals('id', $table1->getAutoIncrementColumn()->getName(), 'Ensure the ID column is correctly identified as the auto increment column.');
        $this->assertCount(1, $table1->getPrimaryKeys(), 'Ensure we have one primary key for the table.');

        // Column creation tests
        $last_updated = $table1->getColumn('last_updated');
        $this->assertTrue($table1->getColumn('id')->isInteger(), 'Ensure our ID column is of type integer.');
        $this->assertEquals('last_updated', $last_updated->getName(), 'Ensure the column name is correct.');
        $this->assertEquals(
            'DEFAULT_GENERATED ON UPDATE CURRENT_TIMESTAMP',
            $last_updated->information_schema->extra,
            'Ensure the extras column is accurate.'
        );

    }

    public function test__CollationTypeOnTable()
    {
        $schemas = $this->builder->processIntoSchemas("
            CREATE TABLE example1 (a TEXT) CHARACTER SET latin1 COLLATE latin1_danish_ci;
        ");

        $table = $schemas[0]->getTables()[0];
        $column = $table->getColumn('a');

        $this->assertEquals('latin1', $column->information_schema->character_set_name);
        $this->assertEquals('latin1_danish_ci', $column->information_schema->collation_name);
    }

    public function test__CollationTypeOnColumn()
    {
        $schemas = $this->builder->processIntoSchemas("
            CREATE TABLE example1 (a TEXT CHARACTER SET latin1 COLLATE latin1_danish_ci);
        ");

        $table = $schemas[0]->getTables()[0];
        $column = $table->getColumn('a');

        $this->assertEquals('latin1', $column->information_schema->character_set_name);
        $this->assertEquals('latin1_danish_ci', $column->information_schema->collation_name);
    }

    public function test__InvalidCollationTypeOnTable()
    {
        $this->expectException(InvalidTokenException::class);

        $schemas = $this->builder->processIntoSchemas("
            CREATE TABLE example1 (a TEXT) CHARACTER SET invalid COLLATE invalid_ci;
        ");
    }
}
