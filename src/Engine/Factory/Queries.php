<?php

declare(strict_types=1);

namespace Cadfael\Engine\Factory;

use Cadfael\Engine\Entity\Database;
use Cadfael\Engine\Entity\Schema;
use Cadfael\Engine\Exception\InvalidColumn;
use Cadfael\Engine\Exception\QueryParseException;
use Cadfael\Engine\Exception\UnknownCharacterSet;
use Cadfael\Engine\Factory\Queries\Fragment;
use Cadfael\Engine\Factory\Queries\Fragments\CreateTable;
use Kodus\SQLSplit\Splitter;
use PHPSQLParser\PHPSQLParser;

/**
 * This class is responsible for taking in a series of queries and determining the database structure that would have
 * been created if the statements where run. Primarily it's interested in queries like:
 *
 * CREATE|DROP DATABASE
 * CREATE|ALTER|DROP TABLE
 * CREATE|DROP USER
 *
 * This might need to be clever and deal with situations like this:
 *     Creating a table, dropping it, creating a new one in its place, altering it afterward.
  */
class Queries extends Fragment
{
    protected PHPSQLParser $parser;

    /**
     * List of all the separated statements from a DDL.
     *
     * @var string[]
     */
    protected array $statements;

    /**
     * Container to hold the object representations of the parsed DDL.
     *
     * @var array
     */
    protected array $structures;

    const DEFAULT_SCHEMA = 'UNKNOWN';

    /**
     * When we build our structure, we need to consider which schema we're dealing with at a specific point.
     * It's possible the script has an inferred schema based on how it's imported, so we'll start from an unknown
     * context.
     *
     * We also need to potentially be able to resolve formulas in places where we normally would expect statements.
     * I suspect collecting a lot of samples of CREATE and ALTER statements will be required.
     *
     * Also need to support generated columns
     *
     * @var string
     */
    protected string $currentSchema = self::DEFAULT_SCHEMA;

    protected Database $database;

    /**
     * We expect a series of statements to be provided as a large string.
     *
     * @param string $version
     * @param string $statements
     */
    public function __construct(string $version, string $statements)
    {
        $this->database = new Database();
        $this->database->setVariables([
            'version' => $version
        ]);
        $this->statements = Splitter::split($statements);
    }

    private function extractSchemaName(array $sub_tree): string
    {
        // In rare situations, this is not set. Fall back to the current schema
        if (!isset($sub_tree['no_quotes'])) {
            // @codeCoverageIgnoreStart
            return $this->currentSchema;
            // @codeCoverageIgnoreEnd
        }

        // Find the no quotes parts, so we can get the database names
        $parts = $sub_tree['no_quotes']['parts'];
        // Throw away the table name
        array_pop($parts);
        return count($parts)
            ? array_pop($parts)
            : $this->currentSchema;
    }

    public function createSchema($schema_name): Schema
    {
        $schema = new Schema($schema_name);
        $schema->setDatabase($this->database);
        return $schema;
    }

    /**
     * @return array<Schema>
     * @throws InvalidColumn
     * @throws UnknownCharacterSet
     * @throws QueryParseException
     */
    public function processIntoSchemas(): array
    {
        // Quick hack because performance schema DIGEST and PHPSQLParser don't agree on some things
        foreach ($this->statements as $statement) {
            $query = str_replace('` . `', '`.`', $statement);
            $parser = new PHPSQLParser($query);

            $parse = $parser->parsed;
            // Ignore anything that fails to parse
            if (!$parse) {
                continue;
            }

            $this->structures['schemas'][$this->currentSchema] ??= $this->createSchema($this->currentSchema);

            // We need to figure out what each statement actually does and how it affects
            // We have a use statement. Prepare to switch context
            if (isset($parse['DROP'])) {
                $fragment = $parse['DROP'];
                switch ($fragment['expr_type']) {
                    case 'database':
                        $database_name = $fragment['sub_tree'][1]['base_expr'];
                        $this->log()->info("Dropping database $database_name");
                        unset($this->structures['schemas'][$database_name]);
                        if ($this->currentSchema == $database_name) {
                            $this->currentSchema = Queries::DEFAULT_SCHEMA;
                        }
                        break;
                    case 'table':
                        // Extract the expression part of drop the query
                        $query_parts = $this->getSingleExpressionType($fragment['sub_tree'], 'expression');
                        // Extract the table part of the query
                        $table_name_parts = $this->getSingleExpressionType($query_parts['sub_tree'], 'table');
                        // Find the no quotes parts, so we can get the database/table names
                        $parts = $table_name_parts['no_quotes']['parts'];

                        $table_name = array_pop($parts);
                        $schema_name = count($parts)
                            ? array_pop($parts)
                            : $this->currentSchema;

                        $this->log()->info("Dropping table $table_name from database $schema_name");
                        // We only process this if we have the specified schema in scope
                        if (isset($this->structures['schemas'][$schema_name])) {
                            $this->structures['schemas'][$schema_name]->removeTableByName($table_name);
                        }
                        break;
                }
            } elseif (isset($parse['USE'])) {
                $this->currentSchema = $parse['USE'][1];
                $this->log()->info("Switching to use database $this->currentSchema");
            } elseif (isset($parse['CREATE'])) {
                if (isset($parse['DATABASE'])) {
                    $name = array_pop($parse['DATABASE']);
                    $this->structures['schemas'][$name] ??= $this->createSchema($name);
                    $this->log()->info("Created new database $name");
                } elseif (isset($parse['TABLE'])) {
                    $create_table = new CreateTable();
                    if ($this->logger) {
                        // @codeCoverageIgnoreStart
                        $create_table->setLogger($this->logger);
                        // @codeCoverageIgnoreEnd
                    }
                    $table = $create_table->process($parse);
                    $schema_name = $this->extractSchemaName($parse['TABLE']);

                    // If the schema doesn't exist, lets create it since it might exist on the target machine
                    if (!isset($this->structures['schemas'][$schema_name])) {
                        $this->structures['schemas'][$schema_name] = $this->createSchema($schema_name);
                    }

                    // If the table already exists, this statement will fail, so we can ignore it (since that's the
                    // behaviour we'd expect from MySQL).
                    // TODO: Consider adding a warning array similar to the MySQL client?
                    if (isset($this->structures['table'][$schema_name][$table->getName()])) {
                        continue;
                    }

                    $this->structures['table'][$schema_name][$table->getName()] = $table;
                    $this->log()->info("Attaching table " . $table->getName() . " to schema $schema_name");
                    $this->structures['schemas'][$schema_name]->addTable($table);
                } elseif (isset($parse['PROCEDURE'])) {
                    $this->log()->info("Ignoring CREATE PROCEDURE operation.");
                } elseif (isset($parse['FUNCTION'])) {
                    $this->log()->info("Ignoring CREATE FUNCTION operation.");
                }
            } elseif (isset($parse['ALTER'])) {
                // Damn ALTER is not parsed properly.
                throw new QueryParseException("Cannot properly parse alter statement yet.");
            } elseif (isset($parse['SET'])) {
                $this->log()->info("Ignoring SET operation.");
            } elseif (isset($parse['TRIGGER'])) {
                $this->log()->info("Ignoring TRIGGER operation.");
            } elseif (isset($parse['DESCRIBE'])) {
                $this->log()->info("Ignoring DESCRIBE operation.");
            } elseif (isset($parse['INSERT']) || isset($parse['SELECT']) || isset($parse['UPDATE'])) {
                $this->log()->info("Ignoring SELECT/INSERT/UPDATE operations.");
            } else {
                throw new QueryParseException("Uncertain on how to handle this statement: " . print_r($parse, true));
            }
        }

        // Throw away the default schema if it doesn't exist
        if (count($this->structures['schemas'][self::DEFAULT_SCHEMA]->getTables()) == 0) {
            unset($this->structures['schemas'][self::DEFAULT_SCHEMA]);
        }

        return array_values($this->structures['schemas']);
    }
}
