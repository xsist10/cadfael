<?php

declare(strict_types=1);

namespace Cadfael\Engine\Entity;

use Cadfael\Engine\Entity;
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
     * @return array<Query>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * @param Query $query
     */
    public function addQuery(Query $query): void
    {
        $query->setSchema($this);
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
