<?php

declare(strict_types=1);

namespace Cadfael\Engine\Check\Table;

use Cadfael\Engine\Check;
use Cadfael\Engine\Entity\Table;
use Cadfael\Engine\Report;

class UnusedIndexes implements Check
{
    public function supports($entity): bool
    {
        return $entity instanceof Table;
    }

    public function run($entity): ?Report
    {
        if (!count($entity->schema_unused_indexes)) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_OK,
                ["No unused indexes found."]
            );
        }

        $messages = [];
        foreach ($entity->schema_unused_indexes as $unused) {
            $messages[] = sprintf(
                "Unused index %s.",
                $unused->index->getName()
            );
            if ($unused->index->isUnique()) {
                $messages[] = sprintf(
                    "However index %s is a UNIQUE constraint",
                    $unused->index->getName(),
                );
            }
        }
        $messages[] = 'This check only indicates that an index has not been used since the server started.';

        return new Report(
            $this,
            $entity,
            Report::STATUS_CONCERN,
            $messages
        );
    }
}
