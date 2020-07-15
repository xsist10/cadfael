<?php

declare(strict_types = 1);

namespace Cadfael\Engine\Entity;

use Cadfael\Engine\Entity;

abstract class Table implements Entity
{
    protected string $name;
    protected string $schema;
    /**
     * @var array<Column>
     */
    protected array $columns = [];
    /**
     * @var array<Index>
     */
    protected array $indexes = [];

    public function __construct(string $schema, string $name)
    {
        $this->name = $name;
        $this->schema = $schema;
    }

    public function setColumns(Column ...$columns): void
    {
        array_walk($columns, function ($column) {
            $column->setTable($this);
        });
        $this->columns = $columns;
    }

    /**
     * @return array<Column>
     */
    public function getColumns(): array
    {
        return $this->columns ?? [];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setIndexes(Index ...$indexes): void
    {
        array_walk($indexes, function ($index) {
            $index->setTable($this);
        });
        $this->indexes = $indexes;
    }

    /**
     * @return array<Index>
     */
    public function getIndexes(): array
    {
        return $this->indexes ?? [];
    }

    /**
     * @return array<Column>
     */
    public function getPrimaryKeys(): array
    {
        return array_filter($this->getColumns() ?? [], function ($column) {
            return $column->isPartOfPrimaryKey();
        });
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
