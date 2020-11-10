<?php

declare(strict_types=1);

namespace Cadfael\Engine\Entity\Account;

class NotClosedProperly
{
    public int $count;
    public float $percentage;

    protected function __construct()
    {
    }

    /**
     * @param array<string> $schema This is a query from events_statements_summary_by_account_by_event_name
     * @return NotClosedProperly
     */
    public static function createFromEventSummary(array $schema)
    {
        $notClosedProperly = new NotClosedProperly();
        $notClosedProperly->count = (int)$schema['not_closed'];
        $notClosedProperly->percentage = (float)$schema['not_closed_perc'];
        return $notClosedProperly;
    }
}
