<?php

declare(strict_types = 1);

namespace Cadfael\Engine;

use Cadfael\Engine\Entity\MySQL\Schema;
use Cadfael\Engine\Entity\MySQL\Table\SchemaAutoIncrementColumns;
use Cadfael\Engine\Entity\MySQL\Table\SchemaRedundantIndexes;
use Cadfael\Engine\Exception\MissingPermissions;
use Doctrine\DBAL\Connection;
use Cadfael\Engine\Entity\MySQL\Table;
use Cadfael\Engine\Entity\MySQL\Column;
use Cadfael\Engine\Entity\Index;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\FetchMode;

class Factory
{
    private Connection $connection;
    /**
     * @var array<array<bool>>
     */
    private $permissions = [];

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    protected function hasPermission(string $schema, string $table): bool
    {
        if (!count($this->permissions)) {
            // First we attempt to figure out what permissions the user has
            $query = 'SHOW GRANTS FOR CURRENT_USER();';
            $this->connection->setFetchMode(FetchMode::NUMERIC);
            $rows = $this->connection->fetchAll($query);
            foreach ($rows as $permission) {
                preg_match('/^GRANT .*?[ALL|SELECT].*? ON (.*?)\.(.*?) TO/', $permission[0], $matches);
                if (count($matches)) {
                    $this->permissions[$matches[1]][$matches[2]] = true;
                }
            }
        }

        return !empty($this->permissions[$schema][$table])
            || !empty($this->permissions[$schema]['*'])
            || !empty($this->permissions['*']['*']);
    }

    /**
     * @throws MissingPermissions
     */
    protected function checkRequiredPermissions(): void
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
                'schema' => 'sys',
                'table'  => 'schema_auto_increment_columns',
            ],
            [
                'schema' => 'sys',
                'table'  => 'schema_redundant_indexes',
            ],
        ];

        foreach ($accesses as $access) {
            if (!$this->hasPermission($access['schema'], $access['table'])) {
                throw new MissingPermissions(sprintf($message, $access['schema'], $access['table']));
            }
        }
    }

    /**
     * @return array<string>
     */
    public function getVariables(): array
    {
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
        $this->checkRequiredPermissions();
        $this->connection->setFetchMode(FetchMode::ASSOCIATIVE);

        $schema = new Schema($database);
        $schema->setVariables($this->getVariables());

        // Collect and generate all the tables
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

        // Collect and generate all sys.* information
        $query = 'SELECT * FROM sys.schema_auto_increment_columns WHERE table_schema=:database';
        $statement = $this->connection->prepare($query);
        $statement->bindValue("database", $database);
        $statement->execute();

        $rows = $statement->fetchAll();
        $schemaAutoIncrementColumns = [];
        foreach ($rows as $row) {
            $schemaAutoIncrementColumns[$row['table_name']] = SchemaAutoIncrementColumns::createFromSys($row);
        }

        $query = 'SELECT * FROM sys.schema_redundant_indexes WHERE table_schema=:database';
        $statement = $this->connection->prepare($query);
        $statement->bindValue("database", $database);
        $statement->execute();

        $rows = $statement->fetchAll();
        $schemaRedundantIndexes = [];
        foreach ($rows as $row) {
            $schemaRedundantIndexes[$row['table_name']][] = SchemaRedundantIndexes::createFromSys($row);
        }

        // Collect and generate all the indexes
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
                $table->setSchemaAutoIncrementColumns($schemaAutoIncrementColumns[$table->getName()]);
            }
            if (!empty($schemaRedundantIndexes[$table->getName()])) {
                $table->setSchemaRedundantIndexes(...$schemaRedundantIndexes[$table->getName()]);
            }
        }

        return $tables;
    }
}
