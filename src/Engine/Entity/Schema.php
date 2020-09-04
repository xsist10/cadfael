<?php

declare(strict_types=1);

namespace Cadfael\Engine\Entity;

use Cadfael\Engine\Entity;

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

    /**
     * @var array<string>
     */
    private array $variables;

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
     * @return array<string>
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * @param array<string> $variables
     */
    public function setVariables(array $variables): void
    {
        $this->variables = $variables;
    }

    public function getVersion(): string
    {
        return $this->variables['version'];
    }

    public function isVirtual(): bool
    {
        return false;
    }
}
