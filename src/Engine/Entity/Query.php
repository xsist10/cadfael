<?php

declare(strict_types=1);

namespace Cadfael\Engine\Entity;

use Cadfael\Engine\Entity;
use Cadfael\Engine\Entity\Query\EventsStatementsSummary;
use Cadfael\Engine\Exception\InvalidTable;
use Cadfael\Engine\Exception\MySQL\UnknownVersion;
use SqlFtw\Parser\Parser;
use SqlFtw\Platform\ClientSideExtension;
use SqlFtw\Platform\Platform;
use SqlFtw\Session\Session;
use SqlFtw\Sql\Command;
use SqlFtw\Sql\Statement;

class Query implements Entity
{
    protected Schema $schema;
    protected string $digest;
    protected EventsStatementsSummary $eventsStatementsSummary;
    /**
     * @var array<Table>
     */
    protected array $tables = [];
    protected Statement $command;

    /**
     * @throws InvalidTable
     * @throws UnknownVersion
     */
    public function __construct(string $digest, Schema $schema)
    {
        $this->digest = $digest;
        $this->schema = $schema;

        $platform = Platform::get(Platform::MYSQL, $this->schema->getDatabase()->getVersion());
        $session = new Session(
            $platform,
            // We need to support these as the digest come with question mark placeholders
            ClientSideExtension::ALLOW_QUESTION_MARK_PLACEHOLDERS_OUTSIDE_PREPARED_STATEMENTS
        );

        // TODO: Move the parser out of Query and just have it accept an injected Command
        $parser = new Parser($session);

        // Returns a Generator
        $commands = $parser->parse($digest);
        // Grab the first one. By default, digest are only a single query
        list($command, $token_list) = $commands->current();
        $this->command = $command;

        $this->linkTablesToQuery();
    }

    public function findParentRecursively($fragment, array $classes_to_find)
    {
        // We need to deal with these as iterable objects we can just walk through. However, most of this is hidden
        // behind private/protected properties.

        $results = [];
        foreach ((array)$fragment as $sub_fragment) {
            // If we've reached scalars and nulls, lets give up
            if (is_null($sub_fragment) or !is_object($sub_fragment)) {
                continue;
            }

            if (in_array(get_class($sub_fragment), $classes_to_find)) {
                $results[] = $fragment;
            }

            $results = array_merge($results, $this->findParentRecursively($sub_fragment, $classes_to_find));
        }

        return $results;
    }

    public function findObjectsRecursively($fragment, array $classes_to_find)
    {
        $results = [];
        // We might be dealing with a simple array. Iterate!
        if (is_array($fragment)) {
            foreach ($fragment as $sub_fragment) {
                if (is_object($sub_fragment) && in_array(get_class($sub_fragment), $classes_to_find)) {
                    $results[] = $sub_fragment;
                }
                $results = array_merge($results, $this->findObjectsRecursively($sub_fragment, $classes_to_find));
            }

            return $results;
        }

        // We need to deal with these as iterable objects we can just walk through. However, most of this is hidden
        // behind private/protected properties.
        foreach ((array)$fragment as $sub_fragment) {
            if (is_null($sub_fragment)) {
                continue;
            }

            if (!is_array($sub_fragment) && !is_object($sub_fragment)) {
                continue;
            }

            if (is_object($sub_fragment)) {
                if (in_array(get_class($sub_fragment), $classes_to_find)) {
                    $results[] = $sub_fragment;
                }
            }

            // We continue to examine all internals, even if they match the classes we're looking for
            $results = array_merge($results, $this->findObjectsRecursively($sub_fragment, $classes_to_find));
        }

        return $results;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->digest;
    }

    /**
     * @codeCoverageIgnore
     *
     * Is this entity virtual (generated rather than stored on disk)?
     *
     * @return bool
     */
    public function isVirtual(): bool
    {
        return true;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * All entities should be able to return a string identifier.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->digest;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @return string
     */
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
     * @throws InvalidTable
     */
    private function linkTablesToQuery(): void
    {
        $tables = $this->findObjectsRecursively(
            $this->command,
            ["SqlFtw\Sql\Dml\TableReference\TableReferenceTable"]
        );

        foreach ($tables as $table) {
            // TODO: We may need to deal with the schema in the future. However we need to refactor much of the concepts
            // in this tool to the database level, not the schema level.
            $table_name = $table->getTable()->getName();
            $alias = $table->getAlias() ?? $table_name;
            if (!$this->schema->hasTable($table_name)) {
                throw new InvalidTable("$table_name is not a table in schema " . $this->schema->getName() . ".");
            }
            $this->tables[$alias] = $this->schema->getTable($table_name);
        }
    }

    /**
     * @return array
     */
    public function getTables(): array
    {
        return array_unique(array_values($this->tables));
    }

    public function getTableByAlias($alias): Table
    {
        return $this->tables[$alias];
    }

    public function fetchColumnsModifiedByFunctions(): array
    {
        // Query contains no tables
        if (!count($this->getTables())) {
            return [];
        }

        $where_parts = $this->findObjectsRecursively(
            $this->command,
            ["SqlFtw\Sql\Expression\ComparisonOperator"]
        );

        // Query contains no where statement
        if (!count($where_parts)) {
            return [];
        }

        $modified_columns = [];
        foreach ($where_parts as $part) {
            // Find all places where there is a function call on a column
            $functions = $this->findObjectsRecursively($part, ['SqlFtw\Sql\Expression\FunctionCall']);
            foreach ($functions as $function) {
                $columns = $this->findObjectsRecursively(
                    $function->getArguments(),
                    ['SqlFtw\Sql\Expression\QualifiedName']
                );
                foreach ($columns as $column) {
                    // In the context here, the getSchema() is the table name and the getName() is the column name
                    $table = $this->getTableByAlias($column->getSchema());
                    $modified_columns[] = $table->getColumn($column->getName());
                }

                $columns = $this->findObjectsRecursively(
                    $function->getArguments(),
                    ['SqlFtw\Sql\Expression\SimpleName']
                );
                foreach ($columns as $column) {
                    // If we have no schema, then we need to assume we only have one table in the query
                    // TODO: We may need to account for the fact that one table in a query is unaliased
                    $table = $this->getTables()[0];
                    $modified_columns[] = $table->getColumn($column->getName());
                }
            }

            // Find all places where there are interval operators. This requires finding an object but then returning
            // the parent object for examination
            $parents = $this->findParentRecursively($part, ['SqlFtw\Sql\Expression\TimeIntervalLiteral']);
            foreach ($parents as $parent) {
                // Find the column involved (we might just be able to look for the left part of the equation
                $columns = $this->findObjectsRecursively(
                    $parent,
                    ['SqlFtw\Sql\Expression\QualifiedName']
                );
                foreach ($columns as $column) {
                    // In the context here, the getSchema() is the table name and the getName() is the column name
                    $table = $this->getTableByAlias($column->getSchema());
                    $modified_columns[] = $table->getColumn($column->getName());
                }
            }
        }

        return array_unique($modified_columns);
    }
}
