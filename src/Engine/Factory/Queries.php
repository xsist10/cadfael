<?php

declare(strict_types=1);

namespace Cadfael\Engine\Factory;

use Cadfael\Engine\Entity\Column;
use Cadfael\Engine\Entity\Column\InformationSchema;
use Cadfael\Engine\Entity\Database;
use Cadfael\Engine\Entity\Index;
use Cadfael\Engine\Entity\Index\Statistics;
use Cadfael\Engine\Entity\Table\InformationSchema as TableInformationSchema;
use Cadfael\Engine\Entity\Schema;
use Cadfael\Engine\Entity\Table;
use Cadfael\Engine\Exception\InvalidColumn;
use Cadfael\Engine\Exception\UnknownCharacterSet;
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

    const DEFAULT_CHARACTER_SET_COLLATIONS = [
        'armscii8'  => 'armscii8_general_ci',
        'ascii'     => 'ascii_general_ci',
        'big5'      => 'big5_chinese_ci',
        'binary'    => 'binary',
        'cp1250'    => 'cp1250_general_ci',
        'cp1251'    => 'cp1251_general_ci',
        'cp1256'    => 'cp1256_general_ci',
        'cp1257'    => 'cp1257_general_ci',
        'cp850'     => 'cp850_general_ci',
        'cp852'     => 'cp852_general_ci',
        'cp866'     => 'cp866_general_ci',
        'cp932'     => 'cp932_japanese_ci',
        'dec8'      => 'dec8_swedish_ci',
        'eucjpms'   => 'eucjpms_japanese_ci',
        'euckr'     => 'euckr_korean_ci',
        'gb18030'   => 'gb18030_chinese_ci',
        'gb2312'    => 'gb2312_chinese_ci',
        'gbk'       => 'gbk_chinese_ci',
        'geostd8'   => 'geostd8_general_ci',
        'greek'     => 'greek_general_ci',
        'hebrew'    => 'hebrew_general_ci',
        'hp8'       => 'hp8_english_ci',
        'keybcs2'   => 'keybcs2_general_ci',
        'koi8r'     => 'koi8r_general_ci',
        'koi8u'     => 'koi8u_general_ci',
        'latin1'    => 'latin1_swedish_ci',
        'latin2'    => 'latin2_general_ci',
        'latin5'    => 'latin5_turkish_ci',
        'latin7'    => 'latin7_general_ci',
        'macce'     => 'macce_general_ci',
        'macroman'  => 'macroman_general_ci',
        'sjis'      => 'sjis_japanese_ci',
        'swe7'      => 'swe7_swedish_ci',
        'tis620'    => 'tis620_thai_ci',
        'ucs2'      => 'ucs2_general_ci',
        'ujis'      => 'ujis_japanese_ci',
        'utf16le'   => 'utf16le_general_ci',
        'utf16'     => 'utf16_general_ci',
        'utf32'     => 'utf32_general_ci',
        'utf8mb3'   => 'utf8mb3_general_ci',
        'utf8mb4'   => 'utf8mb4_0900_ai_ci',
    ];

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

    private function getTableOptions(array $definition): array
    {
        if ($definition['options']) {
            return $definition['options'];
        }
        return [];
    }

    /**
     * Find all array structures where the `expr_type` matches $type.
     *
     * @param array $sub_tree
     * @param string $type
     * @return array
     */
    private function getExpressionType(array $sub_tree, string $type): array
    {
        return array_filter($sub_tree, function ($element) use ($type) {
            return $element['expr_type'] === $type;
        });
    }

    /**
     * Find the first array structures where the `expr_type` matches $type.
     *
     * @param array $sub_tree
     * @param string $type
     * @return array|null
     */
    private function getSingleExpressionType(array $sub_tree, string $type): ?array
    {
        $expressions = $this->getExpressionType($sub_tree, $type);
        return array_shift($expressions);
    }

    /**
     * Find all array structures where the `expr_type` matches $type and the `base_expr` matches $value.
     *
     * @param array $sub_tree
     * @param string $type
     * @param string $value
     * @return array
     */
    private function getExpressionTypeAndValue(array $sub_tree, string $type, string $value): array
    {
        return array_filter($sub_tree, function ($element) use ($type, $value) {
            return $element['expr_type'] === $type && strtolower($element['base_expr']) === strtolower($value);
        });
    }

    /**
     * Find the first array structures where the `expr_type` matches $type and the `base_expr` matches $value.
     *
     * @param array $sub_tree
     * @param string $type
     * @param string $value
     * @return array|null
     */
    private function getSingleExpressionTypeAndValue(array $sub_tree, string $type, string $value): ?array
    {
        $expressions = $this->getExpressionTypeAndValue($sub_tree, $type, $value);
        return array_shift($expressions);
    }

    /**
     * A number of places exist where there is a structure that contains a base_expr as well as the base_expr broken up
     * into unquoted parts. Example:
     *
     * [
     *     'base_expr' => '`schema`.`table`',
     *     'no_quotes' => [
     *         'parts' => [
     *             'schema',
     *             'table'
     *         ]
     *     ]
     * ]
     *
     * This function extracts the `parts` array. If that is not available, it returns an array of one element containing
     * the `base_expr` value.
     *
     * @param array $sub_tree
     * @return array
     */
    private function extractParts(array $sub_tree): array
    {
        if (!isset($sub_tree['no_quotes'])) {
            return [ $sub_tree['base_expr'] ];
        }

        return $sub_tree['no_quotes']['parts'];
    }

    /**
     * A number of places exist where there is a structure that contains a base_expr as well as the base_expr broken up
     * into unquoted parts. Example:
     *
     * [
     *     'base_expr' => '`schema`.`table`',
     *     'no_quotes' => [
     *         'parts' => [
     *             'schema',
     *             'table'
     *         ]
     *     ]
     * ]
     *
     * This function takes this structure and extracts the last entry in the `parts` array since this is often the
     * string we desire (table name, column name, etc.). If no `no_quotes` key exists then we'll fall back to using the
     * `base_expr` key.
     *
     * @param array $sub_tree
     * @return string
     */
    private function extractLastPart(array $sub_tree): string
    {
        $parts = $this->extractParts($sub_tree);
        if (count($parts)) {
            return array_pop($parts);
        }
        return '';
    }

    /**
     * Get the default character set for the table based on it's create definition.
     *
     * @param array $table_def
     * @return string
     */
    private function getTableDefaultCharacterSet(array $table_def): string
    {
        $options = $this->getTableOptions($table_def);
        $character_set = $this->getSingleExpressionType($options, 'character-set');
        if ($character_set) {
            $character_set = $this->getSingleExpressionType($character_set['sub_tree'], 'const');
            return $character_set['base_expr'];
        } else {
            return 'latin1';
        }
    }

    /**
     * Get the default character set collation for the table based on it's create definition. If none is specified in
     * the table creation then we need to consider the default character set of the table and use the default collation
     * for that character set.
     *
     * @param array $table_def
     * @param string $character_set
     * @return string
     * @throws UnknownCharacterSet
     */
    private function getTableDefaultCollation(array $table_def, string $character_set): string
    {
        $options = $this->getTableOptions($table_def);
        $collation = $this->getSingleExpressionType($options, 'collation');
        if ($collation) {
            $collation = $this->getSingleExpressionType($collation['sub_tree'], 'const');
            return $collation['base_expr'];
        } else {
            return $this->getDefaultCollationForCharacterSet($character_set);
        }
    }

    /**
     * Get the default character set collation for a character set.
     *
     * @param string $character_set
     * @return string
     * @throws UnknownCharacterSet
     */
    public function getDefaultCollationForCharacterSet(string $character_set): string
    {
        if (isset(self::DEFAULT_CHARACTER_SET_COLLATIONS[$character_set])) {
            return self::DEFAULT_CHARACTER_SET_COLLATIONS[$character_set];
        }
        throw new UnknownCharacterSet("$character_set is an unknown character set.");
    }

    public function validateCharacterSet(string $character_set): bool
    {
        return isset(self::DEFAULT_CHARACTER_SET_COLLATIONS[$character_set]);
    }

    /**
     * Returns a tuple for table and column
     *
     * @param Table $table
     * @param array $sub_tree
     * @return array
     */
    private function extractTableColumn(Table $table, array $sub_tree): array
    {
        if (!isset($sub_tree['no_quotes'])) {
            return [null, $sub_tree['base_expr']];
        }
        // Find the no quotes parts, so we can get the database/table names
        $parts = $sub_tree['no_quotes']['parts'];

        $column_name = array_pop($parts);
        $table_name = count($parts)
            ? array_pop($parts)
            : $table->getName();

        return [ $table_name, $column_name ];
    }

    /**
     * @param $sub_tree
     * @param Table $table
     * @return void
     * @throws InvalidColumn
     */
    private function processPrimaryKeyColumns($sub_tree, Table $table): void
    {
        $this->processColumnList(
            $sub_tree,
            function ($key) use ($table) {
                $key = $this->extractLastPart($key);
                $this->log()->info("Setting column $key to PRIMARY KEY");
                $table->getColumn($key)->information_schema->column_key = 'PRI';
            }
        );
    }

    // TODO: Cleanup?
    private function extractSchemaTable(array $sub_tree): array
    {
        if (!isset($sub_tree['no_quotes'])) {
            return [null, $sub_tree['base_expr']];
        }
        // Find the no quotes parts, so we can get the database/table names
        $parts = $sub_tree['no_quotes']['parts'];

        $table_name = array_pop($parts);
        $schema_name = count($parts)
            ? array_pop($parts)
            : $this->currentSchema;

        return [ $schema_name, $table_name ];
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
     */
    public function processIntoSchemas(): array
    {
        // Quick hack because performance schema DIGEST and PHPSQLParser don't agree on some things
        foreach ($this->statements as $statement) {
            $query = str_replace('` . `', '`.`', $statement);
            $parser = new PHPSQLParser($query);

            $parse = $parser->parsed;
            $this->structures['database'][$this->currentSchema] ??= $this->createSchema($this->currentSchema);

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
                    $this->structures['database'][$name] ??= $this->createSchema($name);
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
                        $this->structures['database'][$schema_name] = $this->createSchema($schema_name);
                    }

                    // If the table already exists, this statement will fail, so we can ignore it (since that's the
                    // behaviour we'd expect from MySQL).
                    // TODO: Consider adding a warning array similar to the MySQL client?
                    if (isset($this->structures['table'][$schema_name][$table_name])) {
                        continue;
                    }

                    $table = new Table($table_name);
                    $this->structures['table'][$schema_name][$table_name] = $table;

                    // Work out what the default character set and collation for the table are
                    $default_character_set = $this->getTableDefaultCharacterSet($table_def);
                    if (!$this->validateCharacterSet($default_character_set)) {
                        throw new UnknownCharacterSet("Invalid $default_character_set specfied for table $table_name.");
                    }
                    $default_collation = $this->getTableDefaultCollation($table_def, $default_character_set);

                    // Extract and setup columns
                    $column_defs = $this->getExpressionType($table_def['create-def']['sub_tree'], 'column-def');

                    $columns = [];
                    $ordinal = 1;
                    foreach ($column_defs as $column_def) {
                        $sub_tree = $column_def['sub_tree'];

                        $ref = $this->getSingleExpressionType($sub_tree, 'colref');
                        $parts = $this->extractTableColumn($table, $ref);
                        $column_name = array_pop($parts);

                        $type = $this->getSingleExpressionType($sub_tree, 'column-type');

                        $data_type = $this->getSingleExpressionType($type['sub_tree'], 'data-type');
                        $comment = $this->getSingleExpressionType($type['sub_tree'], 'comment');
                        $character_set = $this->getCharacterSet($type);
                        if ($character_set && !$this->validateCharacterSet($character_set)) {
                            throw new UnknownCharacterSet("Invalid $character_set specfied for column $column_name.");
                        }
                        $collation = $this->getCharacterSetCollation($type['sub_tree']);
                        if (!$collation) {
                            if ($character_set) {
                                // Default of the character set
                                $collation = $this->getDefaultCollationForCharacterSet($character_set);
                            } else {
                                // Default of the table
                                $collation = $default_collation;
                            }
                        }
                        if (!$character_set) {
                            $character_set = $default_character_set;
                        }

                        $extras = [];
                        // TODO: Add support for privileges
                        // TODO: Add support for generation_expression
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
                            'character_set_name' => $character_set,
                            'collation_name' => $collation,
                            'datetime_precision' => null,
                            'default' => null,
                            'column_key' => '',
                            'comment' => ($comment ? $comment['base_expr'] : ''),
                        ];

                        // TODO: These two checks aren't being triggered by tests. Investigate.
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
                            // TODO: Cannot create a test for this. Explore more later.
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

    private function processIndexStatement(array $sub_tree, Table $table): void
    {
        $sub_tree = $this->getExpressionType($sub_tree, 'index');
        if (!$sub_tree) {
            return;
        }

        $this->buildIndexes($sub_tree, $table);
    }

    private function processUniqueStatement(array $sub_tree, Table $table): void
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
    private function processPrimaryKeyStatement(array $sub_tree, Table $table): void
    {
        $primary_def = $this->getSingleExpressionType($sub_tree, 'primary-key');
        if (!$primary_def) {
            return;
        }

        $this->processPrimaryKeyColumns($primary_def['sub_tree'], $table);
    }

    private function processColumnList(array $sub_tree, Closure $function): void
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
    private function buildIndexes(array $sub_tree, Table $table, bool $is_unique = false): void
    {
        foreach ($sub_tree as $index_def) {
            $index_name = $this->getSingleExpressionType($index_def['sub_tree'], 'const');

            // So there is a bug in greenlion/php-sql-parser where, if a query has an INDEX defined before PRIMARY KEY,
            // it incorrectly identifies the PRIMARY KEY as an `index` expression instead of a `primary-key` expression.
            // It appears to be related to the loss of the PRIMARY part and so defaults back to just the KEY statement.
            // Bug report: https://github.com/greenlion/PHP-SQL-Parser/issues/337
            // Until this is fix, we'll manually check the base expression
            if (preg_match('/PRIMARY\W+KEY/i', $index_def['base_expr']) !== 0) {
                $this->processPrimaryKeyColumns($index_def['sub_tree'], $table);
                return;
            }

            if (is_null($index_name)) {
                $index_name = "unknown_index";
            } else {
                $index_name = $index_name['base_expr'];
            }
            $this->log()->info("Adding index $index_name to table " . $table->getName() . ".");

            $index = new Index($index_name);
            $index->setTable($table);

            $this->processColumnList(
                $index_def['sub_tree'],
                function ($sub_tree) use ($index, $table, $is_unique) {

                    $key = $this->extractLastPart($sub_tree);
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

    private function getCharacterSet(array $column_type): ?string
    {
        $character_set = $this->getSingleExpressionTypeAndValue($column_type['sub_tree'], 'reserved', 'SET');
        if (empty($character_set)) {
            return null;
        }
        $encoding_tree = $this->getSingleExpressionType($character_set['sub_tree']['sub_tree'], 'colref');
        return $this->extractLastPart($encoding_tree);
    }

    private function getCharacterSetCollation(array $sub_tree): ?string
    {
        $collation = $this->getSingleExpressionType($sub_tree, 'collation');
        if (empty($collation)) {
            return null;
        }

        return $collation['base_expr'];
    }
}
