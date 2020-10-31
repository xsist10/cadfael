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
    protected array $tables;

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
     * @return Table[]
     */
    public function getTables(): array
    {
        return $this->tables;
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

    public function getConnection(): Connection
    {
        return $this->database->getConnection();
    }

    public function isVirtual(): bool
    {
        return false;
    }
}
