<?php

declare(strict_types=1);

namespace Cadfael\Engine\Entity;

use Cadfael\Engine\Entity;
use Cadfael\Engine\Entity\Query\EventsStatementsSummary;
use Cadfael\Engine\Exception\InvalidTable;
use PHPSQLParser\PHPSQLParser;

class Query implements Entity
{
    protected Schema $schema;
    protected string $digest;
    protected EventsStatementsSummary $eventsStatementsSummary;
    protected PHPSQLParser $query_parser;
    /**
     * @var array Table
     */
    protected array $tables = [];

    public function __construct(string $digest)
    {
        $this->digest = $digest;
        // Quick hack because performance schema DIGEST and PHPSQLParser don't agree on some things
        $query = str_replace('` . `', '`.`', $digest);
        $this->query_parser = new PHPSQLParser($query);
    }

    public function getName(): string
    {
        return $this->digest;
    }

    /**
     * Is this entity virtual (generated rather than stored on disk)?
     *
     * @return bool
     */
    public function isVirtual(): bool
    {
        return true;
    }

    /**
     * All entities should be able to return a string identifier.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->digest;
    }

    /**
     * @return Schema
     */
    public function getSchema(): Schema
    {
        return $this->schema;
    }

    /**
     * @param Schema $schema
     */
    public function setSchema(Schema $schema)
    {
        $this->schema = $schema;
    }

    public function getDigest(): string
    {
        return $this->digest;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @return EventsStatementsSummary
     */
    public function getEventsStatementsSummary(): EventsStatementsSummary
    {
        return $this->eventsStatementsSummary;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @param EventsStatementsSummary $eventsStatementsSummary
     */
    public function setEventsStatementsSummary(EventsStatementsSummary $eventsStatementsSummary): void
    {
        $this->eventsStatementsSummary = $eventsStatementsSummary;
    }

    /**
     * This links table objects that a query affects to the query itself
     *
     * @param Schema $schema The contextual schema (if none is specified, the table should be from here)
     * @param Database $database
     * @return void
     * @throws \Cadfael\Engine\Exception\InvalidSchema
     * @throws \Cadfael\Engine\Exception\InvalidTable
     */
    public function linkTablesToQuery(Schema $schema, Database $database)
    {
        $tables = $this->getTableNamesInQuery();
        foreach ($tables as $table) {
            try {
                if (empty($table['schema'])) {
                    $this->tables[$table['alias']] = $schema->getTable($table['name']);
                } else {
                    $this->tables[$table['alias']] = $database->getSchema($table['schema'])->getTable($table['name']);
                }
            } catch (InvalidTable $exception) {
                // It's possible we'll be dealing with a temporary table here.
            }
        }
    }

    /**
     * Return all the tables that are used in an expression
     * @return array
     * @throws \Exception
     */
    public function findTablesInExpression($fragment): array
    {
        if (isset($fragment['expr_type'])) {
            if ($fragment['expr_type'] === 'table') {
                // DIGEST should always contain the `schema`.`table`
                $parts = $fragment['no_quotes']['parts'];
                $table = [];
                $table['name'] = array_pop($parts);
                if (!empty($parts)) {
                    $table['schema'] = array_pop($parts);
                }
                if (!empty($fragment['alias'])) {
                    $table['alias'] = $fragment['alias']['name'];
                } else {
                    $table['alias'] = $table['name'];
                }
                return [ $table ];
            }

            if ($fragment['expr_type'] === 'subquery') {
                return $this->findTablesInExpression($fragment['sub_tree']);
            }

            if ($fragment['expr_type'] === 'table_expression') {
                $tables = [];
                foreach ($fragment['sub_tree'] as $subtree) {
                    $tables = array_merge($tables, $this->findTablesInExpression($subtree));
                }

                return $tables;
            }
        } elseif (isset($fragment['FROM'])) {
            $tables = [];
            foreach ($fragment['FROM'] as $from) {
                $tables = array_merge($tables, $this->findTablesInExpression($from));
            }

            return $tables;
        }

        // It's possible that we've encountered something we didn't prepare for
        throw new \Exception("Uncertain on how to parse this query.");
    }

    /**
     * Return all the tables that are used in this query
     * @return array
     */
    public function getTableNamesInQuery(): array
    {
        return array_unique($this->findTablesInExpression($this->query_parser->parsed), SORT_REGULAR);
    }

    /**
     * @return array
     */
    public function getTables(): array
    {
        return array_values($this->tables);
    }

    public function getTableByAlias($alias): Table
    {
        return $this->tables[$alias];
    }

    /**
     * @return PHPSQLParser
     */
    public function getQueryParser(): PHPSQLParser
    {
        return $this->query_parser;
    }

    protected function extractColumn($tree): array
    {
        // Skip any ? (placeholders) as they are literals
        if ($tree === '?') {
            return [];
        }

        $columns = [];
        if (is_array($tree)) {
            foreach ($tree as $node) {
                $columns += $this->extractColumn($node);
            }
        }
        if (!empty($tree['sub_tree'])) {
            foreach ($tree['sub_tree'] as $sub_tree) {
                $columns += $this->extractColumn($sub_tree);
            }
        }
        if (!empty($tree['expr_type']) && $tree['expr_type'] === 'colref') {
            $columns[$tree['base_expr']] = $tree['no_quotes']['parts'];
        }

        return $columns;
    }

    /**
     * This function searches through a parsed WHERE statement fragment and returns a list of columns (table alias and
     * column name pair) that have been modified by a function expression
     *
     * @param mixed $tree Parsed statement fragment from the WHERE statement
     * @return array tuples of table alias and column name
     */
    protected function fetchColumnsModifiedByFunctionsRecursively(mixed $tree): array
    {
        $functions = [];

        // Deal with sub-statements
        if (!empty($tree['sub_tree'])) {
            foreach ($tree['sub_tree'] as $sub_tree) {
                $functions += $this->fetchColumnsModifiedByFunctionsRecursively($sub_tree);
            }
        }
        // We found a function! Extract the column involved
        if (!empty($tree['expr_type']) && $tree['expr_type'] === 'function') {
            $functions += $this->extractColumn($tree['sub_tree']);
        }

        // We have multiple parts of the statement. Examine each.
        if (is_array($tree)) {
            foreach ($tree as $node) {
                $functions += $this->fetchColumnsModifiedByFunctionsRecursively($node);
            }
        }

        return $functions;
    }

    public function fetchColumnsModifiedByFunctions(): array
    {
        if (empty($this->getQueryParser()->parsed['WHERE'])) {
            return [];
        }

        $query = $this;
        // Remove any empty entries
        return array_filter(
            // Convert the text labels of the table and columns into objects
            array_map(
                function ($column) use ($query) {
                    if (count($column) >= 2) {
                        $table = $query->getTableByAlias(array_shift($column));
                    } else {
                        $table = $query->getTables()[0];
                    }
                    $first_entry = array_shift($column);
                    if ($first_entry === '?') {
                        return [];
                    }

                    return [
                        "table" => $table,
                        "column" => $table->getColumn($first_entry)
                    ];
                },
                array_values(
                    $this->fetchColumnsModifiedByFunctionsRecursively(
                        $this->getQueryParser()->parsed['WHERE']
                    )
                )
            )
        );
    }
}
