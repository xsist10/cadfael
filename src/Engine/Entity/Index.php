<?php

declare(strict_types=1);

namespace Cadfael\Engine\Entity;

use Cadfael\Engine\Entity;
use Cadfael\Engine\Entity\Index\SchemaIndexStatistics;
use Cadfael\Engine\Entity\Index\Statistics;

class Index implements Entity
{
    protected string $name;
    protected Table $table;
    /**
     * @var array<Statistics>
     */
    protected array $statistics = [];
    protected bool $is_unique;
    protected int $size_in_bytes;

    protected SchemaIndexStatistics $schema_index_statistics;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setTable(Table $table): void
    {
        $this->table = $table;
    }

    public function getTable(): Table
    {
        return $this->table;
    }

    /**
     * @return bool
     */
    public function isUnique(): bool
    {
        return $this->is_unique;
    }

    /**
     * @param bool $is_unique
     */
    public function setUnique(bool $is_unique): void
    {
        $this->is_unique = $is_unique;
    }

    /**
     * @codeCoverageIgnore
     * Is this entity virtual (generated rather than stored on disk)?
     *
     * @return bool
     */
    public function isVirtual(): bool
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @param SchemaIndexStatistics $schema_index_statistics
     */
    public function setSchemaIndexStatistics(SchemaIndexStatistics $schema_index_statistics): void
    {
        $this->schema_index_statistics = $schema_index_statistics;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @return SchemaIndexStatistics
     */
    public function getSchemaIndexStatistics(): SchemaIndexStatistics
    {
        return $this->schema_index_statistics;
    }

    /**
     * @return array<Column>
     */
    public function getColumns(): array
    {
        return array_map(
            function (Statistics $statistics) : Column {
                return $statistics->column;
            },
            $this->getStatistics()
        );
    }

    public function setSizeInBytes(int $bytes): void
    {
        $this->size_in_bytes = $bytes;
    }

    public function getSizeInBytes(): int
    {
        return $this->size_in_bytes;
    }

    public function addStatistics(Statistics $statistics): void
    {
        // Set the sequence automatically to the next in line
        $statistics->seq_in_index = count($this->statistics) + 1;
        $this->statistics[] = $statistics;
    }

    public function setStatistics(Statistics ...$statistics): void
    {
        $this->statistics = $statistics;
    }

    public function getStatistics(): array
    {
        return $this->statistics;
    }

    public function __toString(): string
    {
        return (string)$this->table . "." . $this->name;
    }
}
