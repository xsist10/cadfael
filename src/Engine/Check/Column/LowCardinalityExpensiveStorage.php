<?php

declare(strict_types=1);

namespace Cadfael\Engine\Check\Column;

use Cadfael\Engine\Check;
use Cadfael\Engine\Report;
use Cadfael\Engine\Entity\Column;

class LowCardinalityExpensiveStorage implements Check
{
    public function supports($entity): bool
    {
        // This check should only run on columns that are not virtual
        return $entity instanceof Column
            && !$entity->isVirtual()
            && !$entity->getTable()->isVirtual();
    }

    public function run($entity): ?Report
    {
        // We care about large tables with columns with low cardinality that are expensive
        // Lets set up some basic thresholds here.

        // We don't care about columns that aren't particularly expensive in terms of storage cost
        if (!$entity->isString() || $entity->getStorageByteSize() < 12) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_OK
            );
        }

        // We can ignore tables under a thousand records. Optimization should focus on larger tables.
        $num_rows = $entity->getTable()->getNumRows();
        if ($num_rows < 1_000) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_OK
            );
        }

        // Based on the percentage of deviation, we only care if this is very small
        if ($entity->getCardinality() / $num_rows > 0.01) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_OK
            );
        }

        $max = ($num_rows * $entity->getStorageByteSize()) / 1024;
        $format = 'KB';
        if ($max > 1024) {
            $max = ceil($max / 1024);
            $format = 'MB';
        }

        return new Report(
            $this,
            $entity,
            Report::STATUS_CONCERN,
            [
                'This column is expensive to store and has very low cardinality ('
                    . number_format($entity->getCardinality()) . ' possible values).',
                "Consider enumeration or normalizing to save up to " . number_format($max) . "$format ("
                    . $entity->getStorageByteSize() . "B per record)."
            ]
        );
    }

    /**
     * @codeCoverageIgnore
     */
    public function getReferenceUri(): string
    {
        return 'https://github.com/xsist10/cadfael/wiki/Low-Cardinality-Expensive-Storage';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getName(): string
    {
        return 'Low Cardinality with Expensive Storage';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDescription(): string
    {
        return "Using expensive storage options for records that don't vary much means wasted space.";
    }
}
