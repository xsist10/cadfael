<?php

declare(strict_types=1);

namespace Cadfael\Engine\Entity;

use Cadfael\Engine\Entity;

class Index implements Entity
{
    protected string $name;
    protected Table $table;
    /**
     * @var array<Column>
     */
    protected array $columns = [];
    protected bool $is_unique;
    protected int $size_in_bytes;

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
     * Is this entity virtual (generated rather than stored on disk)?
     *
     * @return bool
     */
    public function isVirtual(): bool
    {
        return false;
    }

    public function setSizeInBytes(int $bytes): void
    {
        $this->size_in_bytes = $bytes;
    }

    public function getSizeInBytes(): int
    {
        return $this->size_in_bytes;
    }

    public function setColumns(Column ...$columns): void
    {
        $this->columns = $columns;
    }

    public function __toString(): string
    {
        return (string)$this->table . "." . $this->name;
    }
}
