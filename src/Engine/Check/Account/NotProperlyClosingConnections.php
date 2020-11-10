<?php

declare(strict_types=1);

namespace Cadfael\Engine\Check\Account;

use Cadfael\Engine\Check;
use Cadfael\Engine\Entity\Account;
use Cadfael\Engine\Report;

class NotProperlyClosingConnections implements Check
{
    public function supports($entity): bool
    {
        return $entity instanceof Account;
    }

    public function run($entity): ?Report
    {
        if (!$entity->account_not_closed_properly) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_OK
            );
        }

        return new Report(
            $this,
            $entity,
            Report::STATUS_WARNING,
            [
                "This account has not been closing it's connections to the database properly.",
                "This could lead to your connection pool being exhausted."
            ]
        );
    }
}
