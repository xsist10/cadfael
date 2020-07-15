<?php

declare(strict_types=1);

namespace Cadfael\Engine\Check\MySQL\Table;

use Cadfael\Engine\Check;
use Cadfael\Engine\Entity;
use Cadfael\Engine\Entity\MySQL\Table;
use Cadfael\Engine\Report;

class RedundantIndexes implements Check
{
    public function supports($entity): bool
    {
        return $entity instanceof Table
            && !$entity->isVirtual();
    }

    public function run($entity): ?Report
    {
        if (!count($entity->schema_redundant_indexes)) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_OK,
                ["No redundant indexes found."]
            );
        }

        $messages = [];
        foreach ($entity->schema_redundant_indexes as $redundant_index) {
            $messages[] = sprintf(
                "Redundant index %s (superseded by %s).",
                $redundant_index->redundant_index_name,
                $redundant_index->dominant_index_name
            );
        }
        $messages[] = "A redundant index can probably drop it (unless it's a UNIQUE, in which case the dominant index "
                    . "might be a better candidate for reworking).";
        $messages[] = "Reference: https://dev.mysql.com/doc/refman/5.7/en/sys-schema-redundant-indexes.html";

        return new Report(
            $this,
            $entity,
            Report::STATUS_CONCERN,
            $messages
        );
    }
}
