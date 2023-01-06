<?php

declare(strict_types = 1);

namespace Cadfael\Engine;

use Cadfael\Engine\Entity\Account;
use Cadfael\Engine\Entity\Account\NotClosedProperly;
use Cadfael\Engine\Entity\Account\User;
use Cadfael\Engine\Entity\Database;
use Cadfael\Engine\Entity\Index\Statistics;
use Cadfael\Engine\Entity\Query;
use Cadfael\Engine\Entity\Query\EventsStatementsSummary;
use Cadfael\Engine\Entity\Schema;
use Cadfael\Engine\Entity\Table\AccessInformation;
use Cadfael\Engine\Entity\Table\InnoDbTable;
use Cadfael\Engine\Entity\Table\SchemaAutoIncrementColumn;
use Cadfael\Engine\Entity\Table\SchemaRedundantIndex;
use Cadfael\Engine\Entity\Table\UnusedIndex;
use Cadfael\Engine\Entity\Table;
use Cadfael\Engine\Entity\Column;
use Cadfael\Engine\Entity\Index;
use Cadfael\Engine\Entity\Tablespace;
use Cadfael\Engine\Entity\Index\SchemaIndexStatistics;
use Cadfael\Engine\Exception\MissingPermissions;
use Cadfael\Engine\Exception\MissingInformationSchema;
use Cadfael\Engine\Exception\NonSupportedVersion;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Exception;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @codeCoverageIgnore
 */
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
    private $information_schema_permissions = [];
    /**
     * @var array<string>
     */
    private $schemas = [];
    /**
     * @var array<bool>
     */
    private $table_lookup = [];

    // We don't want to support versions of MySQL before 5.5.
    private const MIN_SUPPORTED_VERSION = '5.5.0';

    // TODO: Move permission related functionality to a subclass

    // These information schema tables require the PROCESS permission to access
    private const PROCESS_TABLE_PERMISSION = [
        'information_schema.innodb_sys_tablespaces',
        'information_schema.innodb_tablespaces'
    ];

    // These are information schema tables that don't require PROCESS rights
    private const INFORMATION_SCHEMA_TABLES = [
        'TABLES',
        'COLUMNS',
        'STATISTICS',
        'SCHEMATA',
        'SCHEMA_PRIVILEGES'
    ];

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function log(): LoggerInterface
    {
        if (!$this->logger) {
            return new NullLogger();
        }
        return $this->logger;
    }

    /**
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * @return array
     * @throws \Doctrine\DBAL\Exception
     */
    private function collectGrants(): array
    {
        $query = 'SHOW GRANTS FOR CURRENT_USER();';
        return $this->connection->fetchAllNumeric($query);
    }

    /**
     * @return array<string>
     * @throws \Doctrine\DBAL\Exception
     */
    private function collectSchemas(): array
    {
        $query = 'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA';
        return array_map(function ($row): string {
            return $row['SCHEMA_NAME'];
        }, $this->connection->fetchAllAssociative($query));
    }

    /**
     * @param Schema $schema
     * @param Database $database
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    private function getEventStatementsSummary(Schema $schema, Database $database): void
    {
        try {
            // Collect all query digests that have been run so far.
            $this->log()->info("Collecting all query digests for schema `" . $schema->getName() . "`.");
            $statement = $this->getConnection()->prepare(EventsStatementsSummary::getQuery());
            $statement->bindValue("schema", $schema->getName());
            $statement->execute();
        } catch (InvalidFieldNameException $exception) {
            // Older versions of MySQL don't have QUERY_SAMPLE_TEXT. Collect everything
            $this->log()->info("Detected version of MySQL performance_schema.events_statements_summary_by_digest without QUERY_SAMPLE_TEXT column.");
            $statement = $this->getConnection()->prepare(EventsStatementsSummary::getQueryWithoutSampleText());
            $statement->bindValue("schema", $schema->getName());
            $statement->execute();
        }

        foreach ($statement->fetchAllAssociative() as $querySummaryByDigest) {
            try {
                $query = new Query($querySummaryByDigest['DIGEST_TEXT']);
                $summary = EventsStatementsSummary::createFromPerformanceSchema($querySummaryByDigest);
                $query->setEventsStatementsSummary($summary);
                $query->linkTablesToQuery($schema, $database);
                $schema->addQuery($query);
            } catch (Exception $exception) {
                $this->log()->warning("Skipping ". $querySummaryByDigest['DIGEST'] .". " . $exception->getMessage());
            }
        }
    }

    private function convertMySQLFuzzyMatchToRegex(string $pattern): string
    {
        return str_replace(['*', '%', '`'], ['.*', '.*', ''], $pattern);
    }

    /**
     * @param string $schema
     * @param string $table
     * @return bool
     * @throws \Doctrine\DBAL\Exception
     */
    public function hasPermission(string $schema, string $table): bool
    {
        if (!count($this->permissions)) {
            $this->log()->info("Collecting GRANTs.");
            // First we attempt to figure out what permissions the user has
            foreach ($this->collectGrants() as $permission) {
                preg_match('/^GRANT .*?(ALL|SELECT).*? ON (.*?)\.(.*?) TO/', $permission[0], $matches);
                if (count($matches)) {
                    // Convert the placeholder syntax in MySQL with RegEx
                    $perm = sprintf(
                        '/%s\.%s/',
                        $this->convertMySQLFuzzyMatchToRegex($matches[2]),
                        $this->convertMySQLFuzzyMatchToRegex($matches[3])
                    );
                    $this->permissions[] = $perm;
                }

                preg_match('/^GRANT .*?PROCESS.*? ON (.*?)\.(.*?) TO/', $permission[0], $matches);
                if (count($matches)) {
                    // Attempt to check if this permission matches certain
                    // tables as they are only accessible with the process
                    // permission.

                    // Convert the placeholder syntax in MySQL with RegEx
                    $pattern = sprintf(
                        '/%s\.%s/',
                        $this->convertMySQLFuzzyMatchToRegex($matches[1]),
                        $this->convertMySQLFuzzyMatchToRegex($matches[2])
                    );

                    foreach (self::PROCESS_TABLE_PERMISSION as $table) {
                        if (preg_match($pattern, $table)) {
                            $this->information_schema_permissions[] = '/' . preg_quote($table) . '/';
                        }
                    }
                }
            }

            // We make the assumption that certain information_schema tables are
            // always accessible
            foreach (self::INFORMATION_SCHEMA_TABLES as $table) {
                $this->information_schema_permissions[] = "/information_schema\.$table/";
            }
        }

        $this->log()->info(sprintf("Checking for permission to access %s.%s.", $schema, $table));
        $subject = "$schema.$table";
        // There is a special list of permissions for some information_schema tables
        if ($schema === 'information_schema') {
            foreach ($this->information_schema_permissions as $pattern) {
                if (preg_match($pattern, $subject)) {
                    return true;
                }
            }
        } else {
            foreach ($this->permissions as $pattern) {
                if (preg_match($pattern, $subject)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param string $schema
     * @return bool
     * @throws \Doctrine\DBAL\Exception
     */
    protected function hasSchema(string $schema): bool
    {
        if (!count($this->schemas)) {
            $this->log()->info("Collecting schemas.");
            // First we attempt to figure out what permissions the user has
            $this->schemas = $this->collectSchemas();
        }

        return in_array($schema, $this->schemas);
    }

    /**
     * @param string $schema
     * @throws \Doctrine\DBAL\Exception
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
                $this->log()->warning(sprintf(
                    "Missing critical permission to access %s.%s.",
                    $access['schema'],
                    $access['table']
                ));
                throw new MissingPermissions(sprintf($message, $access['schema'], $access['table']));
            }
        }
    }

    /**
     * @param Database $database
     * @throws NonSupportedVersion
     */
    protected function checkMySqlVersion(Database $database): void
    {
        $message = '%s is not a supported version of MySQL. Please upgrade to %s or higher.';
        // Get the database version
        $version = $database->getVersion();
        // Check if the version is supported
        if (version_compare($version, self::MIN_SUPPORTED_VERSION, '<')) {
            $this->log()->warning(sprintf($message, $version, self::MIN_SUPPORTED_VERSION));
            throw new NonSupportedVersion(sprintf($message, $version, self::MIN_SUPPORTED_VERSION));
        }
    }

    /**
     * @param Connection $connection
     * @return array<string>
     * @throws \Doctrine\DBAL\Exception
     */
    public function getVariables(Connection $connection): array
    {
        $this->log()->info("Collecting MySQL VARIABLES.");
        $query = 'SHOW VARIABLES';
        $rows = $connection->fetchAllAssociative($query);
        $variables = [];
        foreach ($rows as $row) {
            $variables[$row['Variable_name']] = $row['Value'];
        }
        return $variables;
    }

    /**
     * @param Connection $connection
     * @return array<string>
     * @throws \Doctrine\DBAL\Exception
     */
    public function getStatus(Connection $connection): array
    {
        $this->log()->info("Collecting MySQL GLOBAL STATUS.");
        $query = 'SHOW GLOBAL STATUS';
        $rows = $connection->fetchAllAssociative($query);
        $variables = [];
        foreach ($rows as $row) {
            $variables[$row['Variable_name']] = $row['Value'];
        }
        return $variables;
    }

    /**
     * @param Connection $connection
     * @return array<Account>
     * @throws \Doctrine\DBAL\Exception
     */
    public function getAccounts(Connection $connection): array
    {
        $accounts = [];
        if ($this->hasPermission('mysql', 'user')) {
            $this->log()->info("Collecting MySQL user accounts.");
            $query = 'SELECT * FROM mysql.user';
            foreach ($connection->fetchAllAssociative($query) as $row) {
                $accounts[] = Account::withUser(User::createFromUser($row));
            }
        }
        return $accounts;
    }

    /**
     * @param Connection $connection
     * @param string $schema
     * @param string $table
     * @return bool
     * @throws \Doctrine\DBAL\Exception
     */
    public function doesTableExist(Connection $connection, string $schema, string $table): bool
    {
        if (!count($this->table_lookup)) {
            $this->log()->info("Collecting all table names in database.");
            $query = 'SELECT TABLE_SCHEMA, TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA IN '
                   . '("information_schema", "sys", "mysql", "performance_schema", :schema)';
            $statement = $this->connection->prepare($query);
            $statement->bindValue(":schema", $schema);
            $statement->execute();

            foreach ($statement->fetchAllAssociative() as $row) {
                $key = $row['TABLE_SCHEMA'] . '.' . $row['TABLE_NAME'];
                $this->table_lookup[strtoupper($key)] = true;
            }
        }

        return !empty($this->table_lookup[strtoupper($schema . '.' . $table)]);
    }

    /**
     * @param Connection $connection
     * @return array<Tablespace>
     * @throws \Doctrine\DBAL\Exception
     */
    public function getTablespaces(Connection $connection): array
    {
        $tablespaces = [];
        // MySQL stores innodb tablespace information in different table
        // depending on the version.
        foreach (['innodb_tablespaces', 'innodb_sys_tablespaces'] as $table) {
            $table_exists = $this->doesTableExist($connection, 'information_schema', $table);
            $table_accessible = $this->hasPermission('information_schema', $table);
            if ($table_exists && $table_accessible) {
                $this->log()->info("Collecting MySQL tablespaces from information_schema.$table.");
                $query = "SELECT * FROM information_schema.$table";
                foreach ($connection->fetchAllAssociative($query) as $row) {
                    $tablespaces[] = Tablespace::createFromInformationSchema($row);
                }
            }
        }
        return $tablespaces;
    }

    /**
     * @param Connection $connection
     * @param Schema $schema
     * @return array<InnoDbTable>
     * @throws \Doctrine\DBAL\Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function getInnodbTableMeta(Connection $connection, Schema $schema): array
    {
        $innodb_tables = [];
        // MySQL stores innodb tablespace information in different table
        // depending on the version.
        foreach (['innodb_tables', 'innodb_sys_tables'] as $table) {
            $table_exists = $this->doesTableExist($connection, 'information_schema', $table);
            $table_accessible = $this->hasPermission('information_schema', $table);
            if ($table_exists && $table_accessible) {
                $this->log()->info("Collecting MySQL innodb table meta from information_schema.$table.");
                $query = "SELECT * FROM information_schema.$table WHERE name LIKE :name_pattern";
                $statement = $this->connection->prepare($query);
                $statement->bindValue(":name_pattern", $schema->getName() . "/%");
                $statement->execute();

                $rows = $statement->fetchAllAssociative();
                foreach ($rows as $row) {
                    $table = explode('/', $row['NAME']);
                    $innodb_tables[$table[1]] = InnoDbTable::createFromInformationSchema(
                        $row
                    );
                }
            }
        }
        return $innodb_tables;
    }

    /**
     * @param Connection $connection
     * @param array $schema_names
     * @return Database
     * @throws \Doctrine\DBAL\Exception
     * @throws MissingInformationSchema
     * @throws MissingPermissions
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function buildDatabase(Connection $connection, array $schema_names, bool $load_performance_schema): Database
    {
        $database = new Database($connection);
        $database->setVariables($this->getVariables($connection));
        $database->setStatus($this->getStatus($connection));
        $database->setAccounts(...$this->getAccounts($connection));
        $database->setTablespaces(...$this->getTablespaces($connection));

        $this->checkMySqlVersion($database);

        $can_load_performance_schema = $database->hasPerformanceSchema()
            && $this->hasPermission('performance_schema', '?');

        $schemas = [];
        foreach ($schema_names as $schema_name) {
            $this->checkRequiredPermissions($schema_name);

            $schema = new Schema($schema_name);
            $schemas[] = $schema;

            // Collect and generate all the tables
            $this->log()->info("Collecting information_schema.TABLES.");
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
            $this->log()->info("Collecting information_schema.COLUMNS.");
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
            $this->log()->info("Collecting information_schema.STATISTICS.");
            $statement = $this->connection->prepare(Statistics::getQuery());
            $statement->bindValue("schema", $schema_name);
            $statement->execute();

            $rows = $statement->fetchAllAssociative();
            $indexes = [];
            $indexUnique = [];
            foreach ($rows as $row) {
                $col = $columns[$row['TABLE_NAME']][$row['COLUMN_NAME']];
                $col->setCardinality((int)$row['CARDINALITY']);
                $indexes[$row['TABLE_NAME']][$row['INDEX_NAME']][$row['SEQ_IN_INDEX']] =
                    Statistics::createFromInformationSchema($col, $row);
                $indexUnique[$row['TABLE_NAME']][$row['INDEX_NAME']] = !(bool)$row['NON_UNIQUE'];
            }

            $autoIncrementColumns = [];
            $schemaRedundantIndexes = [];
            $unused_indexes = [];
            $table_access_information = [];
            $table_indexes_objects = [];
            $index_statistics = [];
            if ($this->hasSchema('sys')) {
                if ($this->hasPermission('sys', 'schema_auto_increment_columns')) {
                    // Collect and generate all sys.* information
                    $this->log()->info("Collecting sys.schema_auto_increment_columns.");
                    $statement = $this->connection->prepare(SchemaAutoIncrementColumn::getQuery());
                    $statement->bindValue("schema", $schema_name);
                    $statement->execute();

                    $rows = $statement->fetchAllAssociative();
                    foreach ($rows as $row) {
                        $autoIncrementColumns[$row['table_name']] = SchemaAutoIncrementColumn::createFromSys($row);
                    }
                } else {
                    $this->log()->warning("Missing GRANT to access sys.schema_auto_increment_columns. Skipping.");
                }

                if ($this->hasPermission('sys', 'schema_index_statistics')) {
                    $this->log()->info("Collecting sys.schema_index_statistics.");
                    $statement = $this->connection->prepare(SchemaIndexStatistics::getQuery());
                    $statement->bindValue("schema", $schema_name);
                    $statement->execute();

                    $rows = $statement->fetchAllAssociative();
                    foreach ($rows as $row) {
                        $index_statistics[$row['table_name']][$row['index_name']] =
                            SchemaIndexStatistics::createFromSys($row);
                    }
                } else {
                    $this->log()->warning("Missing GRANT to access sys.schema_redundant_indexes. Skipping.");
                }
            }

            $indexSize = [];
            if ($this->hasPermission('mysql', 'innodb_index_stats')) {
                $this->log()->info("Collecting mysql.innodb_index_stats.");
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
            } else {
                $this->log()->warning("Missing GRANT to access mysql.innodb_index_stats. Skipping.");
            }

            $this->log()->info("Constructing indexes.");
            foreach ($indexes as $table_name => $table_indexes) {
                foreach ($table_indexes as $index_name => $index_columns) {
                    $index = new Index((string)$index_name);
                    $index->setStatistics(...$index_columns);
                    $index->setUnique($indexUnique[$table_name][$index_name]);
                    $index->setSizeInBytes($indexSize[$table_name][$index_name] ?? 0);
                    $table_indexes_objects[$table_name][$index_name] = $index;
                    if (!empty($index_statistics[$table_name][$index_name])) {
                        $index->setSchemaIndexStatistics($index_statistics[$table_name][$index_name]);
                    }
                }
            }

            if ($this->hasSchema('sys') && $this->hasPermission('sys', 'schema_redundant_indexes')) {
                $this->log()->info("Collecting sys.schema_redundant_indexes.");
                $statement = $this->connection->prepare(SchemaRedundantIndex::getQuery());
                $statement->bindValue("schema", $schema_name);
                $statement->execute();

                $rows = $statement->fetchAllAssociative();
                foreach ($rows as $row) {
                    $schemaRedundantIndexes[$row['table_name']][] = SchemaRedundantIndex::createFromSys(
                        $table_indexes_objects[$row['table_name']],
                        $row
                    );
                }
            } else {
                $this->log()->warning("Missing GRANT to access sys.schema_redundant_indexes. Skipping.");
            }

            $innodb_tables = $this->getInnodbTableMeta($connection, $schema);

            if ($load_performance_schema) {
                if (!$can_load_performance_schema) {
                    $message = "Missing critical permission to access performance_schema.?.";
                    $this->log()->warning($message);
                } else {
                    // Collect all indexes that haven't been used
                    $this->log()->info("Collecting performance_schema.table_io_waits_summary_by_index_usage.");
                    $statement = $this->getConnection()->prepare(SchemaUnusedIndex::getQuery());
                    $statement->bindValue("schema", $schema->getName());
                    $statement->execute();
                    foreach ($statement->fetchAllAssociative() as $row) {
                        $index = $table_indexes_objects[$row['object_name']][$row['index_name']];
                        $unused_indexes[$row['object_name']][] = new UnusedIndex($index);
                    }

                    // Collect all accounts who have not been closing connections properly.
                    $message = "Collecting performance_schema.events_statements_summary_by_account_by_event_name.";
                    $this->log()->info($message);
                    $accountsNotClosedProperly = $this->getConnection()
                        ->fetchAllAssociative(NotClosedProperly::getQuery());

                    foreach ($accountsNotClosedProperly as $accountNotClosedProperly) {
                        $account = $this->attemptToFindAccount(
                            $database,
                            $accountNotClosedProperly['user'],
                            $accountNotClosedProperly['host']
                        );
                        $account->setAccountNotClosedProperly(
                            NotClosedProperly::createFromPerformanceSchema($accountNotClosedProperly)
                        );
                    }

                    $this->log()->info("Collecting performance_schema.accounts.");
                    $accountConnections = $this->getConnection()->fetchAllAssociative(User::getQuery());
                    foreach ($accountConnections as $accountConnection) {
                        $account = $database->getAccount($accountConnection['USER'], $accountConnection['HOST']);
                        if (!$account) {
                            $account = Account::withRaw($accountConnection['USER'], $accountConnection['HOST']);
                            $database->addAccount($account);
                        }
                        $account->setCurrentConnections((int)$accountConnection['CURRENT_CONNECTIONS']);
                        $account->setTotalConnections((int)$accountConnection['TOTAL_CONNECTIONS']);
                    }

                    $this->log()->info("Collecting performance_schema.table_io_waits_summary_by_table.");
                    $statement = $this->connection->prepare(AccessInformation::getQuery());
                    $statement->bindValue("schema", $schema->getName());
                    $statement->execute();

                    $access_requests = $statement->fetchAllAssociative();
                    foreach ($access_requests as $access_request) {
                        $table_access_information[$access_request['OBJECT_NAME']] = AccessInformation::createFromIOSummary($access_request);
                    }
                }
            }

            foreach ($tables as $table) {
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
                if (!empty($unused_indexes[$table->getName()])) {
                    $table->setUnusedIndexes(...$unused_indexes[$table->getName()]);
                }
                if (!empty($table_access_information[$table->getName()])) {
                    $table->setAccessInformation($table_access_information[$table->getName()]);
                }
                if (!empty($innodb_tables[$table->getName()])) {
                    $table->setInnoDbTable($innodb_tables[$table->getName()]);
                }
            }

            $schema->setTables(...array_values($tables));
        }

        $database->setSchemas(...$schemas);
        if ($load_performance_schema && $can_load_performance_schema) {
            foreach ($schemas as $schema) {
                $this->getEventStatementsSummary($schema, $database);
            }
        }

        return $database;
    }

    /**
     * @param Database $database
     * @param string $user
     * @param string $host
     * @return Account
     */
    public function attemptToFindAccount(Database $database, string $user, string $host): Account
    {
        $account = $database->getAccount($user, $host);
        if (!$account) {
            $account = Account::withRaw($user, $host);
            $database->addAccount($account);
        }
        return $account;
    }
}
