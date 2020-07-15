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
     * Is this entity virtual (generated rather than stored on disk)?
     *
     * @return bool
     */
    public function isVirtual(): bool
    {
        return false;
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
