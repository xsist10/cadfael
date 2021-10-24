<?php

declare(strict_types = 1);

namespace Cadfael\Engine\Check\Index;

use Cadfael\Engine\Check;
use Cadfael\Engine\Entity\Index;
use Cadfael\Engine\Report;

class IndexPrefix implements Check
{
    public function supports($entity): bool
    {
        return $entity instanceof Index;
    }

    public function run($entity): ?Report
    {
        // Get all the parts of the index that are a string type
        $string_parts = array_filter($entity->getStatistics(), function ($statistics) {
            return $statistics->column->isPrefixAllowed();
        });

        if (!count($string_parts)) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_OK
            );
        }

        $status = Report::STATUS_OK;
        $messages = [];

        foreach ($string_parts as $string_part) {
            $column = $string_part->column;
            $length = $column->information_schema->character_maximum_length;
            // If the length of the string is 12 or less, we can skip this column
            if ($length <= 12) {
                continue;
            }
            // If we have a prefix specified, we can skip this column
            if ($string_part->sub_part) {
                continue;
            }

            // If the column has a high cardinality, then it would make sense to prefix it
            $ratio = $column->getCardinalityRatio();
            if (!$ratio) {
                continue;
            }

            $messages[] = "Column `" . $string_part->column->getName() . "` (length $length) has no index prefix and a "
                        . "cardinality ratio of $ratio.";
            if ($ratio < 1_000) {
                $status = Report::STATUS_CONCERN;
                $messages[] = "Since the column has high cardinality, it's recommended that you limit the index by "
                            . "using a prefix.";
                $messages[] = "This will reduce disk space usage and insert/update performance on this table.";
            }
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
        return 'https://github.com/xsist10/cadfael/wiki/Index-Prefix';
    }

    public function getName(): string
    {
        return 'Index Prefix';
    }

    public function getDescription(): string
    {
        return 'High cardinality indexes with text columns should consider using prefixes.';
    }
}
