<?php

declare(strict_types=1);

namespace Cadfael\Engine\Check\MySQL\Table;

use Cadfael\Engine\Check;
use Cadfael\Engine\Entity;
use Cadfael\Engine\Entity\MySQL\Table;
use Cadfael\Engine\Report;

class MustHavePrimaryKey implements Check
{
    public function supports($entity): bool
    {
        return $entity instanceof Table
            && !$entity->isVirtual();
    }

    public function run($entity): ?Report
    {
        $messages = [
            "Table must have a PRIMARY KEY",
            "Reference: https://federico-razzoli.com/why-mysql-tables-need-a-primary-key.",
        ];
        if ($entity->information_schema->engine === 'InnoDB') {
            $messages[] = "MySQL 8 replication will break if you have InnoDB tables without a PRIMARY KEY.";
        }
        return new Report(
            $this,
            $entity,
            count($entity->getPrimaryKeys()) > 0
                ? Report::STATUS_OK
                : Report::STATUS_CRITICAL,
            $messages
        );
    }
}
