<?php

declare(strict_types=1);

namespace Cadfael\Engine\Factory;

use Cadfael\Engine\Entity\Column;
use Cadfael\Engine\Entity\Column\InformationSchema;
use Cadfael\Engine\Entity\Index;
use Cadfael\Engine\Entity\Index\Statistics;
use Cadfael\Engine\Entity\Table\InformationSchema as TableInformationSchema;
use Cadfael\Engine\Entity\Schema;
use Cadfael\Engine\Entity\Table;
use Cadfael\Engine\Exception\InvalidColumn;
use Cadfael\NullLoggerDefault;
use Cadfael\Utility\Types;
use Closure;
use Kodus\SQLSplit\Splitter;
use PHPSQLParser\PHPSQLParser;
use Psr\Log\LoggerAwareTrait;

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
class Queries
{
    use LoggerAwareTrait, NullLoggerDefault;

    protected PHPSQLParser $parser;

    protected array $statements;

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

    /**
     * We expect a series of statements to be provided as a large string.
     *
     * @param string $statements
     */
    public function __construct(string $statements)
    {
        $this->statements = Splitter::split($statements);
    }

    protected function getExpressionType($sub_tree, $type): array
    {
        return array_filter($sub_tree, function ($element) use ($type) {
            return $element['expr_type'] === $type;
        });
    }

    protected function getSingleExpressionType($sub_tree, $type): ?array
    {
        $expressions = $this->getExpressionType($sub_tree, $type);
        return array_shift($expressions);
    }

    /**
     * @return array<Schema>
     * @throws InvalidColumn
     */
    public function processIntoSchemas(): array
    {
        // Quick hack because performance schema DIGEST and PHPSQLParser don't agree on some things
        foreach ($this->statements as $statement) {
            $query = str_replace('` . `', '`.`', $statement);
            $parser = new PHPSQLParser($query);

            $parse = $parser->parsed;
            $this->structures['database'][$this->currentSchema] ??= new Schema($this->currentSchema);

            // We need to figure out what each statement actually does and how it affects
            // We have a use statement. Prepare to switch context
            if (isset($parse['DROP'])) {
                $fragment = $parse['DROP'];
                switch ($fragment['expr_type']) {
                    case 'database':
                        $database_name = $fragment['sub_tree'][1]['base_expr'];
                        $this->log()->info("Dropping database $database_name");
                        unset($this->structures['database'][$database_name]);
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
                        if (isset($this->structures['database'][$schema_name])) {
                            $this->structures['database'][$schema_name]->removeTableByName($table_name);
                        }
                        break;
                }
            } elseif (isset($parse['USE'])) {
                $this->currentSchema = $parse['USE'][1];
                $this->log()->info("Switching to use database $this->currentSchema");
            } elseif (isset($parse['CREATE'])) {
                if (isset($parse['DATABASE'])) {
                    $name = array_pop($parse['DATABASE']);
                    $this->structures['database'][$name] ??= new Schema($name);
                    $this->log()->info("Created new database $name");
                } elseif (isset($parse['TABLE'])) {
                    $table_def = $parse['TABLE'];

                    $table_name = array_pop($table_def['no_quotes']['parts']);
                    $this->log()->info("Created new table $table_name");
                    $schema_name = count($table_def['no_quotes']['parts'])
                        ? array_pop($table_def['no_quotes']['parts'])
                        : $this->currentSchema;

                    // If the schema doesn't exist, lets create it since it might exist on the target machine
                    if (!isset($this->structures['database'][$schema_name])) {
                        $this->structures['database'][$schema_name] = new Schema($schema_name);
                    }

                    // If the table already exists, this statement will fail, so we can ignore it (since that's the
                    // behaviour we'd expect from MySQL).
                    // TODO: Consider adding a warning array similar to the MySQL client?
                    if (isset($this->structures['table'][$schema_name][$table_name])) {
                        continue;
                    }

                    $table = new Table($table_name);
                    $this->structures['table'][$schema_name][$table_name] = $table;

                    $column_defs = $this->getExpressionType($table_def['create-def']['sub_tree'], 'column-def');

                    // Extract and setup columns
                    $columns = [];
                    $ordinal = 1;
                    foreach ($column_defs as $column_def) {
                        $sub_tree = $column_def['sub_tree'];

                        $ref = $this->getSingleExpressionType($sub_tree, 'colref');
                        $column_name = $ref['base_expr'];

                        $type = $this->getSingleExpressionType($sub_tree, 'column-type');

                        $data_type = $this->getSingleExpressionType($type['sub_tree'], 'data-type');
                        $comment = $this->getSingleExpressionType($type['sub_tree'], 'comment');

                        $extras = [];
                        $definition = [
                            'ordinal_position' => $ordinal,
                            'is_nullable' => $type['nullable'],
                            'column_type' => $data_type['base_expr']
                                . (isset($data_type['length']) ? '(' . $data_type['length'] . ')' : '')
                                . (isset($data_type['unsigned']) ? ' unsigned' : ''),
                            'data_type' => strtolower($data_type['base_expr']),
                            'numeric_precision' => null,
                            'numeric_scale' => null,
                            'character_maximum_length' => null,
                            'character_octet_length' => null,
                            'datetime_precision' => null,
                            'default' => null,
                            'column_key' => '',
                            'comment' => ($comment ? $comment['base_expr'] : ''),
                        ];

                        if ($type['primary']) {
                            $this->log()->info("Setting column $column_name to PRIMARY KEY");
                            $definition['column_key'] = 'PRI';
                        } elseif ($type['unique']) {
                            $definition['column_key'] = 'UNI';
                        }
                        if (isset($data_type['length'])) {
                            if (Types::isNumeric($data_type['base_expr'])) {
                                $definition['numeric_precision'] = (int)$data_type['length'];
                            } elseif (Types::isString($data_type['base_expr'])) {
                                $definition['character_maximum_length'] = (int)$data_type['length'];
                                $definition['character_octet_length'] = min((int)$data_type['length'] * 4, 65535);
                            } elseif (Types::isTime($data_type['base_expr'])) {
                                $definition['datetime_precision'] = (int)$data_type['length'];
                            } else {
                                print "Unknown length to type specific field\n";
                                print_r($data_type);
                            }
                        }
                        if (isset($type['default'])) {
                            $definition['default'] = $type['default'];
                        }

                        if ($type['auto_inc']) {
                            $extras[] = 'auto_increment';
                        }
                        if (str_contains($type['base_expr'], 'DEFAULT CURRENT_TIMESTAMP')) {
                            $extras[] = 'DEFAULT_GENERATED';
                        }
                        if (str_contains($type['base_expr'], 'ON UPDATE CURRENT_TIMESTAMP')) {
                            $extras[] = 'ON UPDATE CURRENT_TIMESTAMP';
                        }
                        $definition['extra'] = implode(' ', $extras);

                        $column = new Column($column_name);
                        $column->information_schema = InformationSchema::createFromStatement($definition);
//                        character_set_name;
//                        collation_name;
//                        privileges;
//                        generation_expression;

                        $columns[] = $column;
                        $ordinal++;
                    }

                    $table->setColumns(...$columns);

                    // Process index statements
                    $this->processIndexStatement($table_def['create-def']['sub_tree'], $table);
                    // Process unique key statements (if not inline)
                    $this->processUniqueStatement($table_def['create-def']['sub_tree'], $table);
                    // Process primary key statement (if not inline)
                    $this->processPrimaryKeyStatement($table_def['create-def']['sub_tree'], $table);


                    $this->log()->info("Setting up information schema for table " . $table->getName());
                    $options = is_array($table_def['options']) ? $table_def['options'] : [];
                    $information_schema = [];
                    foreach ($options as $option) {
                        $type = $this->getSingleExpressionType($option['sub_tree'], 'reserved');
                        $value = $this->getSingleExpressionType($option['sub_tree'], 'const');
                        $information_schema[$type['base_expr']] = $value['base_expr'];
                    }
                    $table->information_schema = TableInformationSchema::createFromInformationSchema(
                        $information_schema
                    );

                    $this->log()->info("Adding table " . $table->getName() . " to schema $schema_name.");
                    $this->structures['database'][$schema_name]->addTable($table);
                }
            } else {
                print "We don't know how to handle this: ";
                print_r($parse);
            }
        }

        // Throw away the default schema if it doesn't exist
        if (count($this->structures['database'][self::DEFAULT_SCHEMA]->getTables()) == 0) {
            unset($this->structures['database'][self::DEFAULT_SCHEMA]);
        }

        return array_values($this->structures['database']);
    }

    public function processIndexStatement(array $sub_tree, Table $table): void
    {
        $sub_tree = $this->getExpressionType($sub_tree, 'index');
        if (!$sub_tree) {
            return;
        }

        $this->buildIndexes($sub_tree, $table);
    }

    public function processUniqueStatement(array $sub_tree, Table $table): void
    {
        $sub_tree = $this->getExpressionType($sub_tree, 'unique-index');
        if (!$sub_tree) {
            return;
        }

        $this->buildIndexes($sub_tree, $table, true);
    }

    /**
     * @param array $sub_tree
     * @param Table $table
     * @return void
     * @throws InvalidColumn
     */
    public function processPrimaryKeyStatement(array $sub_tree, Table $table): void
    {
        $primary_def = $this->getSingleExpressionType($sub_tree, 'primary-key');
        if (!$primary_def) {
            return;
        }

        $this->processColumnList(
            $primary_def['sub_tree'],
            function ($key) use ($table) {
                $key = array_pop($key['no_quotes']['parts']);
                $this->log()->info("Setting column $key to PRIMARY KEY");
                $table->getColumn($key)->information_schema->column_key = 'PRI';
            }
        );
    }

    public function processColumnList(array $sub_tree, Closure $function): void
    {
        // Fetch columns that make up the primary key
        $column_list = $this->getSingleExpressionType($sub_tree, 'column-list');
        // Fetch each index columns
        $keys = $this->getExpressionType($column_list['sub_tree'], 'index-column');
        foreach ($keys as $key) {
            $function($key);
        }
    }

    /**
     * @param array $sub_tree
     * @param Table $table
     * @param bool $is_unique
     * @return void
     * @throws InvalidColumn
     */
    public function buildIndexes(array $sub_tree, Table $table, bool $is_unique = false): void
    {
        foreach ($sub_tree as $index_def) {
            $index_name = $this->getSingleExpressionType($index_def['sub_tree'], 'const');
            // We may be dealing with a PRIMARY KEY. We should skip it as it's dealt with later.
            if (is_null($index_name)) {
                continue;
            }
            $index_name = $index_name['base_expr'];
            $this->log()->info("Adding index $index_name to table " . $table->getName() . ".");

            $index = new Index($index_name);
            $index->setTable($table);

            $this->processColumnList(
                $index_def['sub_tree'],
                function ($sub_tree) use ($index, $table, $is_unique) {

                    $key = array_pop($sub_tree['no_quotes']['parts']);
                    $this->log()->info("Add column $key to INDEX");

                    $column = $table->getColumn($key);
                    $statistic = Statistics::createFromStatement($column);
                    if ($sub_tree['length']) {
                        $statistic->sub_part = (int)$sub_tree['length'];
                    }
                    $index->addStatistics($statistic);
                    $index->setUnique($is_unique);
                }
            );

            if (count($index->getStatistics()) > 0) {
                $table->addIndex($index);
            }
        }
    }
}
