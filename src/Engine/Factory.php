<?php

declare(strict_types = 1);

namespace Cadfael\Engine;

use Cadfael\Engine\Entity\MySQL\Schema;
use Cadfael\Engine\Entity\MySQL\Table\SchemaAutoIncrementColumn;
use Cadfael\Engine\Entity\MySQL\Table\SchemaRedundantIndexes;
use Cadfael\Engine\Exception\MissingPermissions;
use Doctrine\DBAL\Connection;
use Cadfael\Engine\Entity\MySQL\Table;
use Cadfael\Engine\Entity\MySQL\Column;
use Cadfael\Engine\Entity\Index;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\FetchMode;
use Psr\Log\LoggerAwareTrait;

class Factory
{
    use LoggerAwareTrait;

    private Connection $connection;
    /**
     * @var array<string>
     */
    private $permissions = [];
    /**
     * @var array<string>
     */
    private $schemas = [];

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return array<string>
     */
    private function collectGrants(): array
    {
        $query = 'SHOW GRANTS FOR CURRENT_USER();';
        $this->connection->setFetchMode(FetchMode::NUMERIC);
        return $this->connection->fetchAll($query);
    }

    /**
     * @return array<string>
     */
    private function collectSchemas(): array
    {
        $query = 'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA';
        return array_map(function ($row) {
            return $row['SCHEMA_NAME'];
        }, $this->connection->fetchAll($query));
    }

    private function convertMySQLFuzzyMatchToRegex(string $pattern): string
    {
        return str_replace(['*', '%', '`'], ['.*', '.*', ''], $pattern);
    }

    protected function hasPermission(string $schema, string $table): bool
    {
        if (!count($this->permissions)) {
            $this->logger->info("Collecting GRANTs.");
            // First we attempt to figure out what permissions the user has
            foreach ($this->collectGrants() as $permission) {
                preg_match('/^GRANT .*?(ALL|SELECT).*? ON (.*?)\.(.*?) TO/', $permission[0], $matches);
                if (count($matches)) {
                    // Convert the placeholder syntax in MySQL with RegEx
                    $permission = sprintf(
                        '/%s\.%s/',
                        $this->convertMySQLFuzzyMatchToRegex($matches[2]),
                        $this->convertMySQLFuzzyMatchToRegex($matches[3])
                    );
                    $this->permissions[] = $permission;
                }
            }

            // We make the assumption that information_schema is always accessible
            $this->permissions[] = '/information_schema\..*/';
        }

        $this->logger->info(sprintf("Checking for permission to access %s.%s.", $schema, $table));
        $subject = "$schema.$table";
        foreach ($this->permissions as $pattern) {
            if (preg_match($pattern, $subject)) {
                return true;
            }
        }
        return false;
    }

    protected function hasSchema(string $schema): bool
    {
        if (!count($this->schemas)) {
            $this->logger->info("Collecting SCHEMAs.");
            // First we attempt to figure out what permissions the user has
            $this->schemas = $this->collectSchemas();
        }

        return in_array($schema, $this->schemas);
    }

    /**
     * @param string $schema
     * @throws MissingPermissions
     */
    protected function checkRequiredPermissions(string $schema): void
    {
        $message = 'Required access to %s.%s missing for this user account.';
        $accesses = [
            [
                'schema' => 'information_schema',
                'table'  => 'TABLES',
            ],
            [
                'schema' => 'information_schema',
                'table'  => 'COLUMNS',
            ],
            [
                'schema' => 'information_schema',
                'table'  => 'STATISTICS',
            ],
            [
                'schema' => $schema,
                'table'  => '?',
            ],
        ];

        foreach ($accesses as $access) {
            if (!$this->hasPermission($access['schema'], $access['table'])) {
                $this->logger->warning(sprintf(
                    "Missing critical permission to access %s.%s.",
                    $access['schema'],
                    $access['table']
                ));
                throw new MissingPermissions(sprintf($message, $access['schema'], $access['table']));
            }
        }
    }

    /**
     * @return array<string>
     */
    public function getVariables(): array
    {
        $this->logger->info("Collecting MySQL VARIABLES.");
        $query = 'SHOW VARIABLES';
        $rows = $this->connection->fetchAll($query);
        $variables = [];
        foreach ($rows as $row) {
            $variables[$row['Variable_name']] = $row['Value'];
        }
        return $variables;
    }

    /**
     * @param string $database
     * @return array<Table>
     * @throws DBALException|MissingPermissions
     */
    public function getTables(string $database): array
    {
        $this->checkRequiredPermissions($database);
        $this->connection->setFetchMode(FetchMode::ASSOCIATIVE);

        $schema = new Schema($database);
        $schema->setVariables($this->getVariables());

        // Collect and generate all the tables
        $this->logger->info("Collecting information_schema.TABLES.");
        $query = 'SELECT * FROM information_schema.TABLES WHERE TABLE_SCHEMA=:database';
        $statement = $this->connection->prepare($query);
        $statement->bindValue("database", $database);
        $statement->execute();

        $rows = $statement->fetchAll();
        $tables = [];
        foreach ($rows as $row) {
            $table = Table::createFromInformationSchema($row);
            $table->setSchema($schema);
            $tables[] = $table;
        }

        // Collect and generate all the columns
        $this->logger->info("Collecting information_schema.COLUMNS.");
        $query = 'SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=:database';
        $statement = $this->connection->prepare($query);
        $statement->bindValue("database", $database);
        $statement->execute();

        $rows = $statement->fetchAll();
        $columns = [];
        foreach ($rows as $row) {
            $column = Column::createFromInformationSchema($row);
            $columns[$row['TABLE_NAME']][$row['COLUMN_NAME']] = $column;
        }

        $schemaAutoIncrementColumns = [];
        $schemaRedundantIndexes = [];
        if ($this->hasSchema('sys')) {
            if ($this->hasPermission('sys', 'schema_auto_increment_columns')) {
                // Collect and generate all sys.* information
                $this->logger->info("Collecting sys.schema_auto_increment_columns.");
                $query = 'SELECT * FROM sys.schema_auto_increment_columns WHERE table_schema=:database';
                $statement = $this->connection->prepare($query);
                $statement->bindValue("database", $database);
                $statement->execute();

                $rows = $statement->fetchAll();
                foreach ($rows as $row) {
                    $schemaAutoIncrementColumns[$row['table_name']] = SchemaAutoIncrementColumn::createFromSys($row);
                }
            } else {
                $this->logger->warning("Missing GRANT to access sys.schema_auto_increment_columns. Skipping.");
            }

            if ($this->hasPermission('sys', 'schema_redundant_indexes')) {
                $this->logger->info("Collecting sys.schema_redundant_indexes.");
                $query = 'SELECT * FROM sys.schema_redundant_indexes WHERE table_schema=:database';
                $statement = $this->connection->prepare($query);
                $statement->bindValue("database", $database);
                $statement->execute();

                $rows = $statement->fetchAll();
                foreach ($rows as $row) {
                    $schemaRedundantIndexes[$row['table_name']][] = SchemaRedundantIndexes::createFromSys($row);
                }
            } else {
                $this->logger->warning("Missing GRANT to access sys.schema_redundant_indexes. Skipping.");
            }
        }

        // Collect and generate all the indexes
        $this->logger->info("Collecting information_schema.STATISTICS.");
        $query = 'SELECT * FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=:database';
        $statement = $this->connection->prepare($query);
        $statement->bindValue("database", $database);
        $statement->execute();

        $rows = $statement->fetchAll();
        $indexes = [];
        foreach ($rows as $row) {
            $col = $columns[$row['TABLE_NAME']][$row['COLUMN_NAME']];
            $indexes[$row['TABLE_NAME']][$row['INDEX_NAME']][$row['SEQ_IN_INDEX']] = $col;
        }

        $table_indexes_objects = [];
        foreach ($indexes as $table_name => $table_indexes) {
            foreach ($table_indexes as $index_name => $index_columns) {
                $index = new Index((string)$index_name);
                $index->setColumns(...$index_columns);
                $table_indexes_objects[$table_name][] = $index;
            }
        }

        foreach ($tables as $table) {
            if (!empty($columns[$table->getName()])) {
                $table->setColumns(...array_values($columns[$table->getName()]));
            }
            if (!empty($table_indexes_objects[$table->getName()])) {
                $table->setIndexes(...array_values($table_indexes_objects[$table->getName()]));
            }
            if (!empty($schemaAutoIncrementColumns[$table->getName()])) {
                $table->setSchemaAutoIncrementColumn($schemaAutoIncrementColumns[$table->getName()]);
            } else {
                $schemaAutoIncrementColumn = SchemaAutoIncrementColumn::createFromTable($table);
                if ($schemaAutoIncrementColumn) {
                    $table->setSchemaAutoIncrementColumn($schemaAutoIncrementColumn);
                }
            }
            if (!empty($schemaRedundantIndexes[$table->getName()])) {
                $table->setSchemaRedundantIndexes(...$schemaRedundantIndexes[$table->getName()]);
            }
        }

        return $tables;
    }
}
