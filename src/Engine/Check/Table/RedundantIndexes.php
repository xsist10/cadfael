<?php

declare(strict_types=1);

namespace Cadfael\Engine\Check\Table;

use Cadfael\Engine\Check;
use Cadfael\Engine\Entity\Table;
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
        foreach ($entity->schema_redundant_indexes as $redundant) {
            $messages[] = sprintf(
                "Redundant index %s (superseded by %s).",
                $redundant->redundant_index->getName(),
                $redundant->dominant_index->getName()
            );
            if ($redundant->redundant_index->isUnique()) {
                $messages[] = sprintf(
                    "However index %s is UNIQUE, in which case index %s might be a better candidate for reworking",
                    $redundant->redundant_index->getName(),
                    $redundant->dominant_index->getName()
                );
            }
        }
        $messages[] = "Reference: https://dev.mysql.com/doc/refman/8.0/en/sys-schema-redundant-indexes.html";

        return new Report(
            $this,
            $entity,
            Report::STATUS_CONCERN,
            $messages
        );
    }

    public function getReferenceUri(): string
    {
        return 'https://github.com/xsist10/cadfael/wiki/Redundant-Indexes';
    }

    public function getName(): string
    {
        return 'Redundant Indexes';
    }

    public function getDescription(): string
    {
        return 'An index that will never be used by MySQL due to better alternatives.';
    }
}
