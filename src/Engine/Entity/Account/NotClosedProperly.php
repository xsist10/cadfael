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
    public static function createFromPerformanceSchema(array $schema): NotClosedProperly
    {
        return new NotClosedProperly(
            (int)$schema['not_closed'],
            (float)$schema['not_closed_perc']
        );
    }

    public static function getQuery(): string
    {
        return <<<EOF
            SELECT
              ess.user,
              ess.host,
              (a.total_connections - a.current_connections) - ess.count_star as not_closed,
              ((a.total_connections - a.current_connections) - ess.count_star) * 100 /
              (a.total_connections - a.current_connections) as not_closed_perc
            FROM performance_schema.events_statements_summary_by_account_by_event_name ess
            JOIN performance_schema.accounts a on (ess.user = a.user and ess.host = a.host)
            WHERE ess.event_name = 'statement/com/quit'
                AND (a.total_connections - a.current_connections) > ess.count_star
EOF;
    }
}
