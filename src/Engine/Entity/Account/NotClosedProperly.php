<?php

declare(strict_types=1);

namespace Cadfael\Engine\Entity\Account;

/**
 * Class NotClosedProperly
 * @package Cadfael\Engine\Entity\Account
 * @codeCoverageIgnore
 */
class NotClosedProperly
{
    protected function __construct(public int $count, public float $percentage)
    {
    }

    /**
     * @param array<string> $schema This is a query from performance_schema.accounts
     * @return NotClosedProperly
     */
    public static function createFromAccounts(array $schema): NotClosedProperly
    {
        return new NotClosedProperly(
            (int)$schema['not_closed'],
            (float)$schema['not_closed_perc']
        );
    }

    public static function getQuery(): string
    {
        return <<<EOF
            SELECT * FROM performance_schema.accounts WHERE USER IS NOT NULL AND HOST IS NOT NULL
EOF;
    }
}
