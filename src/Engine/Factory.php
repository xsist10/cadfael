<?php

declare(strict_types = 1);

namespace Cadfael\Engine;

use Cadfael\Engine\Entity\Account;
use Cadfael\Engine\Entity\Account\NotClosedProperly;
use Cadfael\Engine\Entity\Database;
use Cadfael\Engine\Entity\Query;
use Cadfael\Engine\Entity\Query\EventsStatementsSummary;
use Cadfael\Engine\Entity\Schema;
use Cadfael\Engine\Entity\Table\AccessInformation;
use Cadfael\Engine\Entity\Table\SchemaAutoIncrementColumn;
use Cadfael\Engine\Entity\Table\SchemaRedundantIndex;
use Cadfael\Engine\Entity\Table\SchemaUnusedIndex;
use Cadfael\Engine\Entity\Table;
use Cadfael\Engine\Entity\Column;
use Cadfael\Engine\Entity\Index;
use Cadfael\Engine\Entity\Index\Statistics;
use Cadfael\Engine\Exception\MissingPermissions;
use Cadfael\Engine\Exception\MissingInformationSchema;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Doctrine\DBAL\FetchMode;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

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
        $this->logger = new NullLogger();
    }

    /**
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
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
        return array_map(function ($row): string {
            return $row['SCHEMA_NAME'];
        }, $this->connection->fetchAll($query));
    }

    private function getEventStatementsSummary(Schema $schema) {
        try {
            // Collect all query digests that have been run so far.
            $statement = $this->getConnection()->prepare("
                SELECT *
                FROM performance_schema.events_statements_summary_by_digest
                WHERE SCHEMA_NAME = :schema
                  AND QUERY_SAMPLE_TEXT NOT LIKE 'show %'
                  AND QUERY_SAMPLE_TEXT NOT LIKE '% information_schema.%'
                  AND QUERY_SAMPLE_TEXT NOT LIKE '% mysql.%'
                  AND QUERY_SAMPLE_TEXT NOT LIKE '% sys.%'
                  AND QUERY_SAMPLE_TEXT NOT LIKE '% performance_schema.%'
            ");
            $statement->bindValue("schema", $schema->getName());
            $statement->execute();
        } catch (InvalidFieldNameException $exception) {
            // Older versions of MySQL don't have QUERY_SAMPLE_TEXT. Collect everything
            $statement = $this->getConnection()->prepare("
                SELECT *
                FROM performance_schema.events_statements_summary_by_digest
                WHERE SCHEMA_NAME = :schema
            ");
            $statement->bindValue("schema", $schema->getName());
            $statement->execute();
        }

        foreach ($statement->fetchAllAssociative() as $querySummaryByDigest) {
            $query = new Query($querySummaryByDigest['DIGEST_TEXT']);
            $summary = EventsStatementsSummary::createFromPerformanceSchema($querySummaryByDigest);
            $query->setEventsStatementsSummary($summary);
            $schema->addQuery($query);
        }
    }

    private function convertMySQLFuzzyMatchToRegex(string $pattern): string
    {
        return str_replace(['*', '%', '`'], ['.*', '.*', ''], $pattern);
    }

    public function hasPermission(string $schema, string $table): bool
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
     * @param Connection $connection
     * @return array<string>
     */
    public function getVariables(Connection $connection): array
    {
        $connection->setFetchMode(FetchMode::ASSOCIATIVE);
        $this->logger->info("Collecting MySQL VARIABLES.");
        $query = 'SHOW VARIABLES';
        $rows = $connection->fetchAll($query);
        $variables = [];
        foreach ($rows as $row) {
            $variables[$row['Variable_name']] = $row['Value'];
        }
        return $variables;
    }

    /**
     * @param Connection $connection
     * @return array<string>
     */
    public function getStatus(Connection $connection): array
    {
        $connection->setFetchMode(FetchMode::ASSOCIATIVE);
        $this->logger->info("Collecting MySQL GLOBAL STATUS.");
        $query = 'SHOW GLOBAL STATUS';
        $rows = $connection->fetchAll($query);
        $variables = [];
        foreach ($rows as $row) {
            $variables[$row['Variable_name']] = $row['Value'];
        }
        return $variables;
    }

    /**
     * @param Connection $connection
     * @return array<Account>
     */
    public function getAccounts(Connection $connection): array
    {
        $accounts = [];
        if ($this->hasPermission('mysql', 'user')) {
            $connection->setFetchMode(FetchMode::ASSOCIATIVE);
            $this->logger->info("Collecting MySQL user accounts.");
            $query = 'SELECT * FROM mysql.user';
            foreach ($connection->fetchAll($query) as $row) {
                $accounts[] = new Account($row['User'], $row['Host']);
            }
        }
        return $accounts;
    }

    /**
     * @param Connection $connection
     * @param array<string> $schema_names
     * @return Database
     * @throws DBALException
     * @throws MissingInformationSchema
     * @throws MissingPermissions
     */
    public function buildDatabase(Connection $connection, array $schema_names): Database
    {
        $database = new Database($connection);
        $database->setVariables($this->getVariables($connection));
        $database->setStatus($this->getStatus($connection));
        $database->setAccounts(...$this->getAccounts($connection));

        $schemas = [];
        foreach ($schema_names as $schema_name) {
            $this->checkRequiredPermissions($schema_name);
            $this->connection->setFetchMode(FetchMode::ASSOCIATIVE);

            $schema = new Schema($schema_name);
            $schemas[] = $schema;

            // Collect and generate all the tables
            $this->logger->info("Collecting information_schema.TABLES.");
            $query = 'SELECT * FROM information_schema.TABLES WHERE TABLE_SCHEMA=:schema';
            $statement = $this->connection->prepare($query);
            $statement->bindValue("schema", $schema_name);
            $statement->execute();

            $rows = $statement->fetchAllAssociative();
            $tables = [];
            foreach ($rows as $row) {
                $table = Table::createFromInformationSchema($row);
                $tables[$table->getName()] = $table;
            }

            // Collect and generate all the columns
            $this->logger->info("Collecting information_schema.COLUMNS.");
            $query = 'SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=:schema';
            $statement = $this->connection->prepare($query);
            $statement->bindValue("schema", $schema_name);
            $statement->execute();

            $rows = $statement->fetchAllAssociative();
            $columns = [];
            foreach ($rows as $row) {
                $column = Column::createFromInformationSchema($row);
                $columns[$row['TABLE_NAME']][$row['COLUMN_NAME']] = $column;
            }

            // Collect and generate all the indexes
            $this->logger->info("Collecting information_schema.STATISTICS.");
            $query = 'SELECT * FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=:schema';
            $statement = $this->connection->prepare($query);
            $statement->bindValue("schema", $schema_name);
            $statement->execute();

            $rows = $statement->fetchAllAssociative();
            $indexes = [];
            $indexUnique = [];
            foreach ($rows as $row) {
                $col = $columns[$row['TABLE_NAME']][$row['COLUMN_NAME']];
                $col->setCardinality((int)$row['CARDINALITY']);
                $indexes[$row['TABLE_NAME']][$row['INDEX_NAME']][$row['SEQ_IN_INDEX']] = $col;
                $indexUnique[$row['TABLE_NAME']][$row['INDEX_NAME']] = !(bool)$row['NON_UNIQUE'];
            }

            $autoIncrementColumns = [];
            $schemaRedundantIndexes = [];
            $schema_unused_indexes = [];
            $table_access_information = [];
            $table_indexes_objects = [];
            $index_statistics = [];
            if ($this->hasSchema('sys')) {
                if ($this->hasPermission('sys', 'schema_auto_increment_columns')) {
                    // Collect and generate all sys.* information
                    $this->logger->info("Collecting sys.schema_auto_increment_columns.");
                    $query = 'SELECT * FROM sys.schema_auto_increment_columns WHERE table_schema=:schema';
                    $statement = $this->connection->prepare($query);
                    $statement->bindValue("schema", $schema_name);
                    $statement->execute();

                    $rows = $statement->fetchAllAssociative();
                    foreach ($rows as $row) {
                        $autoIncrementColumns[$row['table_name']] = SchemaAutoIncrementColumn::createFromSys($row);
                    }
                } else {
                    $this->logger->warning("Missing GRANT to access sys.schema_auto_increment_columns. Skipping.");
                }

                if ($this->hasPermission('sys', 'schema_redundant_indexes')) {
                    $this->logger->info("Collecting sys.schema_redundant_indexes.");
                    $query = 'SELECT * FROM sys.schema_redundant_indexes WHERE table_schema=:schema';
                    $statement = $this->connection->prepare($query);
                    $statement->bindValue("schema", $schema_name);
                    $statement->execute();

                    $rows = $statement->fetchAllAssociative();
                    foreach ($rows as $row) {
                        $schemaRedundantIndexes[$row['table_name']][] = SchemaRedundantIndex::createFromSys(
                            $tables[$row['table_name']],
                            $row
                        );
                    }
                } else {
                    $this->logger->warning("Missing GRANT to access sys.schema_redundant_indexes. Skipping.");
                }

                if ($this->hasPermission('sys', 'schema_index_statistics')) {
                    $this->logger->info("Collecting sys.schema_index_statistics.");
                    $query = 'SELECT * FROM sys.schema_index_statistics WHERE table_schema=:schema';
                    $statement = $this->connection->prepare($query);
                    $statement->bindValue("schema", $schema_name);
                    $statement->execute();

                    $rows = $statement->fetchAllAssociative();
                    foreach ($rows as $row) {
                        $index_statistics[$row['table_name']][$row['index_name']] = Statistics::createFromSys($row);
                    }
                } else {
                    $this->logger->warning("Missing GRANT to access sys.schema_redundant_indexes. Skipping.");
                }
            }

            $indexSize = [];
            if ($this->hasPermission('mysql', 'innodb_index_stats')) {
                // Collect the size of indexes
                $statement = $this->getConnection()->prepare("
                    SELECT
                      table_name,
                      index_name,
                      stat_value
                    FROM mysql.innodb_index_stats
                    WHERE index_name NOT IN ('PRIMARY', 'GEN_CLUST_INDEX')
                        AND stat_name = 'size'
                        AND database_name = :schema
                    ORDER BY table_name, index_name;
                ");
                $statement->bindValue("schema", $schema->getName());
                $statement->execute();
                foreach ($statement->fetchAllAssociative() as $row) {
                    $size = $row['stat_value'] * $database->getVariables()['innodb_page_size'];
                    $indexSize[$row['table_name']][$row['index_name']] = $size;
                }
            }

            foreach ($indexes as $table_name => $table_indexes) {
                foreach ($table_indexes as $index_name => $index_columns) {
                    $index = new Index((string)$index_name);
                    $index->setColumns(...$index_columns);
                    $index->setUnique($indexUnique[$table_name][$index_name]);
                    $index->setSizeInBytes($indexSize[$table_name][$index_name] ?? 0);
                    $table_indexes_objects[$table_name][$index_name] = $index;
                    if (!empty($index_statistics[$table_name][$index_name])) {
                        $index->setStatistics($index_statistics[$table_name][$index_name]);
                    }
                }
            }

            if (!$database->hasPerformanceSchema() || !$this->hasPermission('performance_schema', '?')) {
                $message = "Missing critical permission to access performance_schema.?.";
                $this->logger->warning($message);
            } else {
                // Collect all indexes that haven't been used
                $statement = $this->getConnection()->prepare("
                    SELECT object_schema, object_name, index_name
                    FROM performance_schema.table_io_waits_summary_by_index_usage
                    WHERE index_name IS NOT NULL
                        AND index_name != 'PRIMARY'
                        AND count_star = 0
                        AND object_schema = :schema
                    ORDER BY object_schema, object_name;
                ");
                $statement->bindValue("schema", $schema->getName());
                $statement->execute();
                foreach ($statement->fetchAllAssociative() as $row) {
                    $index = $table_indexes_objects[$row['object_name']][$row['index_name']];
                    $schema_unused_indexes[$row['object_name']][] = new SchemaUnusedIndex($index);
                }

                $this->getEventStatementsSummary($schema);

                // Collect all accounts who have not been closing connections properly.
                $accountsNotClosedProperly = $this->getConnection()->fetchAll("
                    SELECT
                      ess.user,
                      ess.host,
                      (a.total_connections - a.current_connections) - ess.count_star as not_closed,
                      ((a.total_connections - a.current_connections) - ess.count_star) * 100 /
                      (a.total_connections - a.current_connections) as not_closed_perc
                    FROM performance_schema.events_statements_summary_by_account_by_event_name ess
                    JOIN performance_schema.accounts a on (ess.user = a.user and ess.host = a.host)
                    WHERE ess.event_name = 'statement/com/quit'
                        AND (a.total_connections - a.current_connections) > ess.count_star
                ");

                foreach ($accountsNotClosedProperly as $accountNotClosedProperly) {
                    $account = $database->getAccount(
                        $accountNotClosedProperly['user'],
                        $accountNotClosedProperly['host']
                    );
                    if (!$account) {
                        $account = new Account($accountNotClosedProperly['user'], $accountNotClosedProperly['host']);
                        $database->addAccount($account);
                    }
                    $account->setAccountNotClosedProperly(
                        NotClosedProperly::createFromEventSummary($accountNotClosedProperly)
                    );
                }

                $accountConnections = $this->getConnection()->fetchAll("
                    SELECT * FROM performance_schema.accounts WHERE USER IS NOT NULL AND HOST IS NOT NULL
                ");
                foreach ($accountConnections as $accountConnection) {
                    $account = $database->getAccount($accountConnection['USER'], $accountConnection['HOST']);
                    if (!$account) {
                        $account = new Account($accountConnection['USER'], $accountConnection['HOST']);
                        $database->addAccount($account);
                    }
                    $account->setCurrentConnections((int)$accountConnection['CURRENT_CONNECTIONS']);
                    $account->setTotalConnections((int)$accountConnection['TOTAL_CONNECTIONS']);
                }

                $query = "
                    SELECT OBJECT_NAME, COUNT_READ, COUNT_WRITE
                    FROM performance_schema.table_io_waits_summary_by_table
                    WHERE OBJECT_SCHEMA=:schema
                ";
                $statement = $this->connection->prepare($query);
                $statement->bindValue("schema", $schema_name);
                $statement->execute();

                $access_requests = $statement->fetchAllAssociative();
                foreach ($access_requests as $access_request) {
                    $table_access_information[$access_request['OBJECT_NAME']] = new AccessInformation(
                        (int)$access_request['COUNT_READ'],
                        (int)$access_request['COUNT_WRITE']
                    );
                }
            }

            foreach ($tables as $name => $table) {
                if (!empty($columns[$table->getName()])) {
                    $table->setColumns(...array_values($columns[$table->getName()]));
                }
                if (!empty($table_indexes_objects[$table->getName()])) {
                    $table->setIndexes(...array_values($table_indexes_objects[$table->getName()]));
                }
                if (!empty($autoIncrementColumns[$table->getName()])) {
                    $table->setSchemaAutoIncrementColumn($autoIncrementColumns[$table->getName()]);
                } else {
                    $schemaAutoIncrementColumn = SchemaAutoIncrementColumn::createFromTable($table);
                    if ($schemaAutoIncrementColumn) {
                        $table->setSchemaAutoIncrementColumn($schemaAutoIncrementColumn);
                    }
                }
                if (!empty($schemaRedundantIndexes[$table->getName()])) {
                    $table->setSchemaRedundantIndexes(...$schemaRedundantIndexes[$table->getName()]);
                }
                if (!empty($schema_unused_indexes[$table->getName()])) {
                    $table->setUnusedRedundantIndexes(...$schema_unused_indexes[$table->getName()]);
                }
                if (!empty($table_access_information[$table->getName()])) {
                    $table->setAccessInformation($table_access_information[$table->getName()]);
                }
            }

            $schema->setTables(...array_values($tables));
        }

        $database->setSchemas($schemas);
        return $database;
    }
}
