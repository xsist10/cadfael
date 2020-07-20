<?php

declare(strict_types=1);

namespace Cadfael\Engine\Entity;

abstract class Schema
{
    /**
     * @var string
     */
    protected string $name;

    /**
     * @var array<Table>
     */
    protected array $tables;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @param Table ...$tables
     */
    public function setTables(Table ...$tables): void
    {
        array_walk($tables, function ($table) {
            $table->setSchema($this);
        });
        $this->tables = $tables;
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

    abstract public function isVirtual(): bool;
    abstract public function getVersion(): string;
}
