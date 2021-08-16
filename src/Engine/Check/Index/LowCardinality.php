<?php

declare(strict_types=1);

namespace Cadfael\Engine\Check\Index;

use Cadfael\Engine\Check;
use Cadfael\Engine\Entity\Index;
use Cadfael\Engine\Report;

class LowCardinality implements Check
{
    public function supports($entity): bool
    {
        return $entity instanceof Index;
    }

    public function run($entity): ?Report
    {
        // If the index is unique, then the cardinality is as high as it can be
        if ($entity->isUnique()) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_OK
            );
        }

        // If the index is also a primary key, we can skip it
        // However we currently don't build those, so no worries :)

        // Identify the cardinality as a ratio of the size of the table
        // Cardinality in older version of MySQL aren't distinct per column
        $table_size = $entity->getTable()->information_schema->table_rows;
        $cardinality = $entity->getColumns()[0]->cardinality;

        $ratio = $table_size / $cardinality;

        // The closer to 1 this value is, the more unique it is.
        // The larger it is, the less unique the results are.
        $messages = [
            "The ratio of cardinality for this index is $ratio."
        ];
        $status = Report::STATUS_OK;
        if ($ratio >= 10_000) {
            $status = Report::STATUS_WARNING;
            $messages[] = "This seems particularly high and will cause longer querying times or the index being ignored (wasted space/processing).";
        }
        else if ($ratio >= 1_000) {
            $status = Report::STATUS_CONCERN;
            $messages[] = "This seems high and may cause longer querying times or the index being ignored (wasted space/processing).";
        }

        return new Report(
            $this,
            $entity,
            $status,
            $messages
        );
    }
}
