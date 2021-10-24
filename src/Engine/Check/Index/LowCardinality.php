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

        // Get the first column since it's cardinality is the most important
        $column = $entity->getColumns()[0];

        // Not sure why cardinality can be 0 for now. Until I find out why,
        // I will just skip the check if I find it with a notice
        // TODO: Find out why and see if we can make a judgement
        if ($column->getCardinality() === 0) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_OK,
                [ "Cardinality for the first column is 0. Not sure how to handle this yet." ]
            );
        }

        // Identify the cardinality as a ratio of the size of the table
        // Cardinality in older version of MySQL aren't distinct per column
        $ratio = $column->getCardinalityRatio();

        // The closer to 1 this value is, the more unique it is.
        // The larger it is, the less unique the results are.
        $messages = [
            "The ratio of cardinality for this index is $ratio (lower is better).",
            "It is calculated by dividing the table size by column cardinality."
        ];
        $status = Report::STATUS_OK;
        if ($ratio >= 1_000) {
            $status = Report::STATUS_CONCERN;
            $messages[] = "This seems particularly high.";
            $messages[] = "It may cause longer querying times or the index being ignored (wasted space/processing).";
        }
        if ($ratio >= 10_000) {
            $status = Report::STATUS_WARNING;
        }

        return new Report(
            $this,
            $entity,
            $status,
            $messages
        );
    }

    public function getReferenceUri(): string
    {
        return 'https://github.com/xsist10/cadfael/wiki/Low-Cardinality';
    }

    public function getName(): string
    {
        return 'Low Cardinality Index';
    }

    public function getDescription(): string
    {
        return 'Large tables with indexes that start with low cardinality columns are inefficient.';
    }
}
