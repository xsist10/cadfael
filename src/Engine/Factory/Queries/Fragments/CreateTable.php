<?php

declare(strict_types=1);

namespace Cadfael\Engine\Factory\Queries\Fragments;

use Cadfael\Engine\Entity\Column;
use Cadfael\Engine\Entity\Column\InformationSchema;
use Cadfael\Engine\Entity\Index;
use Cadfael\Engine\Entity\Index\Statistics;
use Cadfael\Engine\Entity\Table;
use Cadfael\Engine\Entity\Table\InformationSchema as TableInformationSchema;
use Cadfael\Engine\Exception\InvalidColumn;
use Cadfael\Engine\Exception\UnknownCharacterSet;
use Cadfael\Engine\Factory\Queries\Fragment;
use Cadfael\Utility\Types;
use Closure;

class CreateTable extends Fragment
{
    /**
     * @throws UnknownCharacterSet
     * @throws InvalidColumn
     */
    public function process(array $fragment): Table
    {
        $table_def = $fragment['TABLE'];

        $table_name = array_pop($table_def['no_quotes']['parts']);
        $this->log()->info("Created new table $table_name");

        $table = new Table($table_name);

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
            $create_column = new CreateColumn();
            if ($this->logger) {
                $create_column->setLogger($this->logger);
            }
            $columns[] = $create_column->process($column_def, $ordinal, $default_character_set, $default_collation);
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

        return $table;
    }

    /**
     * Proxy function to return an appropriate array of table options.
     *
     * @param array $definition
     * @return array
     */
    private function getTableOptions(array $definition): array
    {
        if ($definition['options']) {
            return $definition['options'];
        }
        return [];
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
     * Returns a tuple for table and column
     *
     * @param Table $table
     * @param array $sub_tree
     * @return array
     */
    private function extractTableColumnName(Table $table, array $sub_tree): array
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
}
