<?php

declare(strict_types=1);


namespace Cadfael\Tests\Factory;

use Cadfael\Engine\Check\Column\SaneAutoIncrement;
use Cadfael\Engine\Check\Table\MustHavePrimaryKey;
use Cadfael\Engine\Exception\InvalidColumn;
use Cadfael\Engine\Exception\QueryParseException;
use Cadfael\Engine\Factory\Queries;
use Cadfael\Engine\Exception\UnknownCharacterSet;
use Cadfael\Engine\Report;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class QueriesTest extends TestCase
{
    protected function attachLogger(Queries $queries): void
    {
        // TODO: Remove later
        $log = new Logger('debugger');
        $log->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        $queries->setLogger($log);
    }

    public function testCreateTableWithoutSchemaHasDefault()
    {
        $queries = new Queries("8.1.0", "
            CREATE TABLE `example1` (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
            );
        ");

        $schemas = $queries->processIntoSchemas();
        $this->assertCount(1, $schemas, "Ensure only one schema was created.");
        $this->assertEquals(Queries::DEFAULT_SCHEMA, $schemas[0]->getName(), "Ensure our schema has the default name.");
    }

    public function testCreateTableWithSchemaHasCorrectName()
    {
        $queries = new Queries("8.1.0", "
            USE example_db;
            CREATE TABLE `example1` (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
            );
        ");

        $schemas = $queries->processIntoSchemas();
        $this->assertCount(1, $schemas, "Ensure only one schema was created.");
        $this->assertEquals('example_db', $schemas[0]->getName(), "Ensure our schema has the correct name.");
    }

    public function testCreateDropSchema()
    {
        $queries = new Queries("8.1.0", "
            CREATE DATABASE example_db;
            DROP DATABASE example_db;
        ");

        $schemas = $queries->processIntoSchemas();
        $this->assertCount(0, $schemas, "Ensure no schema was created.");
    }

    public function testCreateDropSchemaWithUse()
    {
        $queries = new Queries("8.1.0", "
            CREATE DATABASE example_db;
            USE example_db;
            DROP DATABASE example_db;
        ");

        $schemas = $queries->processIntoSchemas();
        $this->assertCount(0, $schemas, "Ensure no schema was created.");
    }

    public function testCreateDropSchemaWithTable()
    {
        $queries = new Queries("8.1.0", "
            CREATE DATABASE example_db;
            USE example_db;
            CREATE TABLE `example1` (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
            );
            DROP DATABASE example_db;
        ");

        $schemas = $queries->processIntoSchemas();
        $this->assertCount(0, $schemas, "Ensure no schema was created.");
    }

    public function testCreateDropTableWithExplicitSchema()
    {
        $queries = new Queries("8.1.0", "
            CREATE TABLE `example_db`.`example1` (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
            );
            DROP TABLE `example_db`.`example1`;
        ");

        $schemas = $queries->processIntoSchemas();
        $this->assertCount(1, $schemas, "Ensure a schema was created.");
        $this->assertCount(0, $schemas[0]->getTables(), "Ensure the schema has no tables.");
    }

    public function testCreateDropTableWithDefaultSchema()
    {
        $queries = new Queries("8.1.0", "
            CREATE TABLE `example1` (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
            );
            DROP TABLE `example1`;
        ");

        $schemas = $queries->processIntoSchemas();
        $this->assertCount(0, $schemas, "We don't create an empty default schema.");
    }

    public function testCreateDropSchemaWithUseAndCreateTable()
    {
        $queries = new Queries("8.1.0", "
            CREATE DATABASE example_db;
            USE example_db;
            DROP DATABASE example_db;
            
            CREATE TABLE `example1` (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
            );
        ");

        $schemas = $queries->processIntoSchemas();
        $this->assertCount(1, $schemas, "Ensure only one schema was created.");
        $this->assertEquals(Queries::DEFAULT_SCHEMA, $schemas[0]->getName(), "Ensure our schema has the correct name.");
    }

    public function testCreateTableWithExplicitSchemaName()
    {
        $queries = new Queries("8.1.0", "
            CREATE TABLE `example_db`.`example1` (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
            );
        ");

        $schemas = $queries->processIntoSchemas();
        $this->assertCount(1, $schemas, "Ensure only one schema was created.");
        $this->assertEquals('example_db', $schemas[0]->getName(), "Ensure our schema has the correct name.");
    }

    public function testPrimaryKeyDefinitionSeparateStatementBackticks()
    {
        $queries = new Queries("8.1.0", "
            DROP TABLE IF EXISTS `table_empty_in_tablespace`;
            CREATE TABLE `table_empty_in_tablespace` (
                `id` int NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                PRIMARY KEY (`id`)
            ) /*!50100 TABLESPACE `innodb_system` */ ENGINE=InnoDB;
        ");


        $schemas = $queries->processIntoSchemas();
        $table = $schemas[0]->getTables()[0];

        $this->assertCount(2, $table->getColumns(), 'Ensure the correct number of columns were created.');
        $this->assertEquals('id', $table->getAutoIncrementColumn()->getName(), 'Ensure the ID column is correctly identified as the auto increment column.');
        $this->assertCount(1, $table->getPrimaryKeys(), 'Ensure we have one primary key for the table.');
    }

    public function testPrimaryKeyDefinitionInline()
    {
        $queries = new Queries("8.1.0", "
            CREATE TABLE `example1` (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
            );
        ");

        $schemas = $queries->processIntoSchemas();
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
        $queries = new Queries("8.1.0", "
            CREATE TABLE `table_with_large_text_index` (
                id INT NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                INDEX idx_name (name),
                PRIMARY KEY (id)
            ) ENGINE=InnoDB;
        ");

        $schemas = $queries->processIntoSchemas();
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
        $queries = new Queries("8.1.0", "
            CREATE TABLE `example2` (
                id INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(64) NOT NULL,
                email VARCHAR(255) NOT NULL,
                UNIQUE idx_email (email),
                INDEX idx_name (name)
            );
        ");

        $schemas = $queries->processIntoSchemas();
        $table = $schemas[0]->getTables()[0];

        $this->assertCount(3, $table->getColumns(), 'Ensure the correct number of columns were created.');
        $this->assertCount(2, $table->getIndexes(), 'Ensure we have detected the correct number of indexes');
        $this->assertTrue($table->getIndexes()[1]->isUnique(), 'Ensure we have a unique index.');
    }

    public function testIndexDefinitionStatementWithParts()
    {
        $queries = new Queries("8.1.0", "
            CREATE TABLE `example2` (
                id INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(64) NOT NULL,
                INDEX idx_name (name(20))
            );
        ");


        $schemas = $queries->processIntoSchemas();
        $table = $schemas[0]->getTables()[0];

        $indexes = $table->getIndexes();
        $statistics = $indexes[0]->getStatistics();

        $this->assertCount(1, $indexes, 'Ensure we have detected the correct number of indexes');
        $this->assertEquals(20, $statistics[0]->sub_part, 'Ensure we capture the index sub_part accurately.');
    }

    public function testIndexDefinitionStatementWithInvalidColumn()
    {
        $this->expectException(InvalidColumn::class);

        $queries = new Queries("8.1.0", "
            CREATE TABLE `example2` (
                id INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                INDEX idx_non_existent_column (non_existent_column)
            );
        ");

        $queries->processIntoSchemas();
    }

    public function testPrimaryKeyDefinitionStatementWithInvalidColumn()
    {
        $this->expectException(InvalidColumn::class);

        $queries = new Queries("8.1.0", "
            CREATE TABLE `example2` (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                PRIMARY KEY (non_existent_column)
            );
        ");

        $queries->processIntoSchemas();
    }

    public function testCharacterSetNameCollationImplicit()
    {
        $queries = new Queries("8.1.0", "
            CREATE TABLE `example` (
                a VARCHAR(255) CHARACTER SET utf8mb4,
                b VARCHAR(255) CHARACTER SET latin1,
                c VARCHAR(255)
            );
        ");

        $schemas = $queries->processIntoSchemas();
        $table = $schemas[0]->getTables()[0];
        $columns = $table->getColumns();
        $this->assertEquals("utf8mb4", $columns[0]->information_schema->character_set_name);
        $this->assertEquals("utf8mb4_0900_ai_ci", $columns[0]->information_schema->collation_name);
        $this->assertEquals("latin1", $columns[1]->information_schema->character_set_name);
        $this->assertEquals("latin1_swedish_ci", $columns[1]->information_schema->collation_name);
        $this->assertEquals("latin1", $columns[2]->information_schema->character_set_name);
        $this->assertEquals("latin1_swedish_ci", $columns[2]->information_schema->collation_name);
    }

    public function testCharacterSetNameExplicit()
    {
        $queries = new Queries("8.1.0", "
            CREATE TABLE `example` (
                a VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                b VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci,
                c VARCHAR(255),
                d INT
            ) CHARACTER SET latin1 COLLATE latin1_bin;
        ");

        $schemas = $queries->processIntoSchemas();
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
        $queries = new Queries("8.1.0", "
            CREATE TABLE IF NOT EXISTS example (
                a VARCHAR(255) CHARSET ascii,
                b VARCHAR(255) CHARSET ascii COLLATE ascii_bin,
                c TEXT CHARACTER SET ascii,
                d VARCHAR(32) CHARACTER SET ascii
            );
        ");

        $schemas = $queries->processIntoSchemas();
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
        $this->expectException(UnknownCharacterSet::class);

        $queries = new Queries("8.1.0", "
            CREATE TABLE `example` (
                a VARCHAR(255) CHARACTER SET invalid_character_set
            );
        ");

        $queries->processIntoSchemas();
    }

    public function testInvalidCharacterSetInTable()
    {
        $this->expectException(UnknownCharacterSet::class);

        $queries = new Queries("8.1.0", "
            CREATE TABLE `example` (
                a VARCHAR(255)
            ) CHARACTER SET invalid_character_set;
        ");

        $queries->processIntoSchemas();
    }

    public function testInvalidCharacterSetWithCollationInTable()
    {
        $this->expectException(UnknownCharacterSet::class);

        $queries = new Queries("8.1.0", "
            CREATE TABLE `example` (
                a VARCHAR(255)
            ) CHARACTER SET invalid_character_set COLLATE latin1_bin;
        ");

        $queries->processIntoSchemas();
    }

    public function testSetOperationIgnore()
    {
        $queries = new Queries("8.1.0", "
            SET @total_tax = (SELECT SUM(tax) FROM taxable_transactions);
        ");

        $schemas = $queries->processIntoSchemas();
        $this->assertCount(0, $schemas, "No schemas should be generated for this.");
    }

    public function testCreateProcedureIgnore()
    {
        $queries = new Queries("8.1.0", "
            DROP TRIGGER trigger_name;
        ");

        $schemas = $queries->processIntoSchemas();
        $this->assertCount(0, $schemas, "No schemas should be generated for this.");
    }

    public function testAlterAddColumn()
    {
        $this->markTestSkipped("Skipped until ALTER is better supported");

        $queries = new Queries("8.1.0", "
            CREATE TABLE example1 (a INT);
            ALTER TABLE example1 ADD COLUMN b VARCHAR(32);
        ");

        $this->attachLogger($queries);
        $schemas = $queries->processIntoSchemas();
        $this->assertCount(1, $schemas, "One default schemas should be generated for this.");
        $table = $schemas[0]->getTables()[0];
        $columns = $table->getColumns();
        $this->assertCount(2, $columns, "Two tables should be generated.");
    }

    public function testMultiplePrimaryKeyColumns()
    {
        $queries = new Queries("8.1.0", "
            CREATE TABLE example1 (
              a INT NOT NULL,
              b BIGINT NOT NULL,
              PRIMARY KEY (a, b)
            );
        ");

        $schemas = $queries->processIntoSchemas();
        $table = $schemas[0]->getTables()[0];

        $this->assertCount(2, $table->getColumns());
        $this->assertCount(0, $table->getIndexes());
        $this->assertCount(2, $table->getPrimaryKeys());
    }

    // TODO: Work on a solution for alters
    public function testCreateTableWithAlter()
    {
        $this->expectException(QueryParseException::class);

        $queries = new Queries("8.1.0", "
            CREATE TABLE example1 (
              a INT NOT NULL
            );

            ALTER TABLE example1 ADD COLUMN b INT NOT NULL;
        ");

        $queries->processIntoSchemas();
    }

    public function testLargeSequence()
    {
        $queries = new Queries("8.1.0", "
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
            
            DROP TABLE IF EXISTS `table_empty`;
            CREATE TABLE `table_empty` (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB;
        ");


        $schemas = $queries->processIntoSchemas();
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
        $this->assertTrue($table1->getColumn('id')->isInteger(), 'Ensure our ID column is of type integer.');
        $this->assertEquals('last_updated', $table1->getColumns()[4]->getName(), 'Ensure the column name is correct.');

    }
}
