<?php

declare(strict_types = 1);

namespace Cadfael\Engine\Entity;

use Cadfael\Engine\Entity;

abstract class Table implements Entity
{
    /**
     * @var string
     */
    protected string $name;
    /**
     * @var Schema
     */
    protected Schema $schema;
    /**
     * @var array<Column>
     */
    protected array $columns = [];
    /**
     * @var array<Index>
     */
    protected array $indexes = [];

    public function __construct(string $name)
    {
        $this->name = $name;
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
    public function setSchema(Schema $schema): void
    {
        $this->schema = $schema;
    }

    /**
     * @param Column ...$columns
     */
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

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param Index ...$indexes
     */
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

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->name;
    }
}
