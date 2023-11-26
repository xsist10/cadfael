<?php

declare(strict_types=1);

namespace Cadfael\Engine\Entity;

use Cadfael\Engine\Entity;
use Cadfael\Engine\Exception\InvalidSchema;
use Cadfael\Engine\Exception\InvalidTable;
use Doctrine\DBAL\Connection;

class Schema implements Entity
{
    /**
     * @var string
     */
    protected string $name;

    /**
     * @var array<Table>
     */
    protected array $tables = [];

    /**
     * @var array<Query>
     */
    protected array $queries = [];

    protected Database $database;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @param Table ...$tables
     */
    public function setTables(Table ...$tables): void
    {
        array_walk($tables, function (Table $table) {
            $table->setSchema($this);
        });
        $this->tables = $tables;
    }

    /**
     * @param Table $table
     */
    public function addTable(Table $table): void
    {
        if (!$this->hasTable($table->getName())) {
            $table->setSchema($this);
            $this->tables[] = $table;
        }
    }

    /**
     * @param string $table_name
     */
    public function removeTableByName(string $table_name): void
    {
        if ($this->hasTable($table_name)) {
            $this->tables = array_filter($this->tables, function ($table) use ($table_name) {
                return $table->getName() !== $table_name;
            });
        }
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @return Table[]
     */
    public function getTables(): array
    {
        return $this->tables;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @param $name
     * @return bool
     */
    public function hasTable($name): bool
    {
        foreach ($this->getTables() as $table) {
            if ($table->getName() === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @param $name
     * @return Table
     * @throws InvalidTable
     */
    public function getTable($name): Table
    {
        foreach ($this->getTables() as $table) {
            if ($table->getName() === $name) {
                return $table;
            }
        }

        throw new InvalidTable("Invalid table specified: $name");
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @return array<Query>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @param Query $query
     */
    public function addQuery(Query $query): void
    {
        $this->queries[] = $query;
    }

    /**
     * @param Database $database
     */
    public function setDatabase(Database $database): void
    {
        $this->database = $database;
    }

    /**
     * @return Database
     */
    public function getDatabase(): Database
    {
        return $this->database;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->database->getConnection();
    }

    public function isVirtual(): bool
    {
        return false;
    }
}
