<?php

declare(strict_types = 1);

namespace Cadfael\Engine\Entity;

use Cadfael\Engine\Entity;

abstract class Column implements Entity
{
    protected string $name;
    protected Table $table;

    public function getName(): string
    {
        return $this->name;
    }

    public function setTable(Table $table): void
    {
        $this->table = $table;
    }

    public function __toString(): string
    {
        return (string)$this->table . "." . $this->name;
    }

    public function getTable(): Table
    {
        return $this->table;
    }

    abstract public function isVirtual(): bool;
    abstract public function isPartOfPrimaryKey() : bool;
    abstract public function isSigned(): bool;
    abstract public function isAutoIncrementing(): bool;
    abstract public function isInteger(): bool;
    abstract public function isNumeric(): bool;
    abstract public function getStorageByteSize(): float;
}
