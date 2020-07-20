<?php

declare(strict_types=1);

namespace Cadfael\Engine\Entity\MySQL;

use Cadfael\Engine\Entity\Schema as BaseSchema;

class Schema extends BaseSchema
{
    /**
     * @var array<string>
     */
    private array $variables;

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
