<?php

declare(strict_types=1);

namespace Cadfael\Engine\Entity\Query;

use DateTimeImmutable;

/**
 * Class EventsStatementsSummary
 * @package Cadfael\Engine\Entity\Query
 * @codeCoverageIgnore
 *
 * DTO of a record from performance_schema.events_statements_summary_by_account_by_event_name
 */
class EventsStatementsSummary
{
    public string $schema_name;
    public int $count_star;
    public int $sum_timer_wait;
    public int $min_timer_wait;
    public int $avg_timer_wait;
    public int $max_timer_wait;
    public int $sum_lock_time;
    public int $sum_errors;
    public int $sum_warnings;
    public int $sum_rows_affected;
    public int $sum_rows_sent;
    public int $sum_rows_examined;
    public int $sum_created_tmp_disk_tables;
    public int $sum_created_tmp_tables;
    public int $sum_select_full_join;
    public int $sum_select_full_range_join;
    public int $sum_select_range;
    public int $sum_select_range_check;
    public int $sum_select_scan;
    public int $sum_sort_merge_passes;
    public int $sum_sort_range;
    public int $sum_sort_rows;
    public int $sum_sort_scan;
    public int $sum_no_index_used;
    public int $sum_no_good_index_used;
    public DateTimeImmutable $first_seen;
    public DateTimeImmutable $last_seen;
    public int $quantile_95;
    public int $quantile_99;
    public int $quantile_999;
    public string $query_sample_text;
    public DateTimeImmutable $query_sample_seen;
    public int $query_sample_timer_wait;

    protected function __construct()
    {
    }

    /**
     * @param array<string> $schema This is a raw record from
     * performance_schema.events_statements_summary_by_account_by_event_name
     * @return EventsStatementsSummary
     */
    public static function createFromPerformanceSchema(array $schema)
    {
        $summary = new EventsStatementsSummary();
        $summary->schema_name = $schema['SCHEMA_NAME'];
        $summary->count_star = (int)$schema['COUNT_STAR'];
        $summary->sum_timer_wait = (int)$schema['SUM_TIMER_WAIT'];
        $summary->min_timer_wait = (int)$schema['MIN_TIMER_WAIT'];
        $summary->avg_timer_wait = (int)$schema['AVG_TIMER_WAIT'];
        $summary->max_timer_wait = (int)$schema['MAX_TIMER_WAIT'];
        $summary->sum_lock_time = (int)$schema['SUM_LOCK_TIME'];
        $summary->sum_errors = (int)$schema['SUM_ERRORS'];
        $summary->sum_warnings = (int)$schema['SUM_WARNINGS'];
        $summary->sum_rows_affected = (int)$schema['SUM_ROWS_AFFECTED'];
        $summary->sum_rows_sent = (int)$schema['SUM_ROWS_SENT'];
        $summary->sum_rows_examined = (int)$schema['SUM_ROWS_EXAMINED'];
        $summary->sum_created_tmp_disk_tables = (int)$schema['SUM_CREATED_TMP_DISK_TABLES'];
        $summary->sum_created_tmp_tables = (int)$schema['SUM_CREATED_TMP_TABLES'];
        $summary->sum_select_full_join = (int)$schema['SUM_SELECT_FULL_JOIN'];
        $summary->sum_select_full_range_join = (int)$schema['SUM_SELECT_FULL_RANGE_JOIN'];
        $summary->sum_select_range = (int)$schema['SUM_SELECT_RANGE'];
        $summary->sum_select_range_check = (int)$schema['SUM_SELECT_RANGE_CHECK'];
        $summary->sum_select_scan = (int)$schema['SUM_SELECT_SCAN'];
        $summary->sum_sort_merge_passes = (int)$schema['SUM_SORT_MERGE_PASSES'];
        $summary->sum_sort_range = (int)$schema['SUM_SORT_RANGE'];
        $summary->sum_sort_rows = (int)$schema['SUM_SORT_ROWS'];
        $summary->sum_sort_scan = (int)$schema['SUM_SORT_SCAN'];
        $summary->sum_no_index_used = (int)$schema['SUM_NO_INDEX_USED'];
        $summary->sum_no_good_index_used = (int)$schema['SUM_NO_GOOD_INDEX_USED'];
        $summary->first_seen = self::convertToDateTime($schema['FIRST_SEEN']);
        $summary->last_seen = self::convertToDateTime($schema['LAST_SEEN']);
        if (isset($schema['QUERY_SAMPLE_TEXT'])) {
            $summary->quantile_95 = (int)$schema['QUANTILE_95'];
            $summary->quantile_99 = (int)$schema['QUANTILE_99'];
            $summary->quantile_999 = (int)$schema['QUANTILE_999'];
            $summary->query_sample_text = $schema['QUERY_SAMPLE_TEXT'];
            $summary->query_sample_seen = self::convertToDateTime($schema['QUERY_SAMPLE_SEEN']);
            $summary->query_sample_timer_wait = (int)$schema['QUERY_SAMPLE_TIMER_WAIT'];
        }

        return $summary;
    }

    private static function convertToDateTime(string $date) : DateTimeImmutable
    {
        $result = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $date);
        if ($result) {
            return $result;
        }

        return new DateTimeImmutable();
    }
}
