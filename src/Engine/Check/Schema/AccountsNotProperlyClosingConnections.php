<?php

declare(strict_types=1);

namespace Cadfael\Engine\Check\Schema;

use Cadfael\Engine\Check;
use Cadfael\Engine\Entity\Schema;
use Cadfael\Engine\Report;
use Cadfael\Sampling\PerformanceSchema;

class AccountsNotProperlyClosingConnections implements Check
{
    public function supports($entity): bool
    {
        return $entity instanceof Schema;
    }

    public function run($entity): ?Report
    {
        $accounts = PerformanceSchema::getAccountsNotProperlyClosingConnections($entity->getConnection());
//        var_dump($accounts);
//
//        user,
//        host,
//        not_closed,
//        not_closed_perc

        return new Report(
            $this,
            $entity,
            Report::STATUS_OK,
            [ "" ]
        );
    }
}
