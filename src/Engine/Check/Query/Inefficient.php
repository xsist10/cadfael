<?php

declare(strict_types=1);

namespace Cadfael\Engine\Check\Query;

use Cadfael\Engine\Check;
use Cadfael\Engine\Entity\Query;
use Cadfael\Engine\Report;

class Inefficient implements Check
{
    public function supports($entity): bool
    {
        return $entity instanceof Query;
    }

    public function run($entity): ?Report
    {
        $summary = $entity->getEventsStatementsSummary();

        $messages = [];
        $status = Report::STATUS_OK;

        // High ration of examined vs sent rows
        $examined_ratio = $summary->sum_rows_examined / ($summary->sum_rows_sent > 0 ? $summary->sum_rows_sent : 1);
        if ($examined_ratio >= 100) {
            $messages[] = sprintf(
                "%0.2f rows examined for every 1 returned.",
                $examined_ratio
            );
            $messages[] = "Better indexes with higher cardinality would improve this.";
            $status = Report::STATUS_CONCERN;
        }

        $full_scan_ratio = min($summary->sum_select_scan / $summary->count_star, 1.0);
        if ($full_scan_ratio >= 0.2) {
            $messages[] = sprintf("%0.2f%% of queries perform full scans.", $full_scan_ratio * 100);
            $status = Report::STATUS_WARNING;
        }

        $no_index_ratio = $summary->sum_no_index_used / $summary->count_star;
        if ($no_index_ratio >= 0.2) {
            $messages[] = sprintf("%0.2f%% of queries used no index at all.", $no_index_ratio * 100);
            $status = Report::STATUS_WARNING;
        }

        $bad_index_ratio = $summary->sum_no_good_index_used / $summary->count_star;
        if ($bad_index_ratio >= 0.2) {
            $messages[] = sprintf(
                "%0.2f%% of queries used had only bad indexes to choose from (low cardinality).",
                $bad_index_ratio * 100
            );
            $status = Report::STATUS_WARNING;
        }

        $full_sort_ratio = $summary->sum_sort_scan / $summary->count_star;
        if ($full_sort_ratio >= 0.2) {
            $messages[] = sprintf("%0.2f%% of queries perform full sorts.", $full_sort_ratio * 100);
            $status = Report::STATUS_WARNING;
        }

        if ($summary->sum_created_tmp_disk_tables) {
            $messages[] = "Temporary table was stored on disk which has a high I/O cost. "
                        . "Consider refactoring your query to avoid large temporary tables.";
            $status = Report::STATUS_WARNING;
        }

        // Otherwise this column is fine
        return new Report(
            $this,
            $entity,
            $status,
            $messages
        );
    }

    /**
     * @codeCoverageIgnore
     */
    public function getReferenceUri(): string
    {
        return 'https://github.com/xsist10/cadfael/wiki/Inefficient-Query';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getName(): string
    {
        return 'Inefficient Query';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDescription(): string
    {
        return "Queries run that haven't perform well since the server restarted.";
    }
}
