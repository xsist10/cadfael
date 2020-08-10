<?php

declare(strict_types=1);

namespace Cadfael\Engine\Check\MySQL\Table;

use Cadfael\Engine\Check;
use Cadfael\Engine\Entity\MySQL\Table;
use Cadfael\Engine\Exception\MissingSysData;
use Cadfael\Engine\Report;

class AutoIncrementCapacity implements Check
{

    public function supports($entity): bool
    {
        return $entity instanceof Table
            && !is_null($entity->schema_auto_increment_column)
            && !$entity->isVirtual();
    }

    public function run($entity): ?Report
    {
        $auto_increment = $entity->schema_auto_increment_column;

        // It is possible that we don't have access to the `sys` schema with the credentials supplied.
        if (is_null($auto_increment)) {
            throw new MissingSysData("Missing information from `sys.schema_auto_increment_columns`");
        }

        $percentage = $auto_increment->auto_increment_ratio * 100;
        $percentage_used = sprintf("%0.2f%%", $percentage);

        $data = [
            'total'      => $auto_increment->max_value,
            'used'       => $auto_increment->auto_increment ?? 0,
            'available'  => $auto_increment->max_value - $auto_increment->auto_increment,
            'percentage' => $percentage_used,
        ];

        // Some results from sys.schema_auto_increment_columns contain undef
        // values instead of 0 for new tables. We'll just skip these are they are
        // already well within threshold (since it means they're empty).
        if (!$auto_increment->auto_increment) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_OK,
                [],
                $data
            );
        }

        $messages = [];

        $status = Report::STATUS_OK;
        if ($percentage >= 60) {
            $status = Report::STATUS_WARNING;
        }

        if ($percentage >= 80) {
            $status = Report::STATUS_CRITICAL;
        }

        if (
            // We only care about entries that throw an error
            $status !== Report::STATUS_OK
            // If they have a small capacity then they aren't likely needed to grow
            && $auto_increment->max_value <= 1024
        ) {
            $status = Report::STATUS_WARNING;
            $messages[] = "Your auto increment column has a small capacity so it may be intentional.";
        }

        return new Report(
            $this,
            $entity,
            $status,
            $messages,
            $data
        );
    }
}
