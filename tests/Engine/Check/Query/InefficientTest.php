<?php

declare(strict_types=1);


namespace Cadfael\Tests\Engine\Check\Query;

use Cadfael\Engine\Check\Query\Inefficient;
use Cadfael\Engine\Entity\Query\EventsStatementsSummary;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\BaseTest;

class InefficientTest extends BaseTest
{
    public function providerQueryData() {
        $schema = $this->createSchema();
        $template = [
            'SCHEMA_NAME' => $schema->getName(),
            'COUNT_STAR' => '100',
            'SUM_TIMER_WAIT' => '100416406000',
            'MIN_TIMER_WAIT' => '41991629000',
            'AVG_TIMER_WAIT' => '50208203000',
            'MAX_TIMER_WAIT' => '58424777000',
            'SUM_LOCK_TIME' => '5000000',
            'SUM_ERRORS' => '0',
            'SUM_WARNINGS' => '0',
            'SUM_ROWS_AFFECTED' => '2000',
            'SUM_ROWS_SENT' => '2000',
            'SUM_ROWS_EXAMINED' => '2000',
            'SUM_CREATED_TMP_DISK_TABLES' => '0',
            'SUM_CREATED_TMP_TABLES' => '2',
            'SUM_SELECT_FULL_JOIN' => '0',
            'SUM_SELECT_FULL_RANGE_JOIN' => '0',
            'SUM_SELECT_RANGE' => '0',
            'SUM_SELECT_RANGE_CHECK' => '0',
            'SUM_SELECT_SCAN' => '0',
            'SUM_SORT_MERGE_PASSES' => '0',
            'SUM_SORT_RANGE' => '0',
            'SUM_SORT_ROWS' => '0',
            'SUM_SORT_SCAN' => '0',
            'SUM_NO_INDEX_USED' => '0',
            'SUM_NO_GOOD_INDEX_USED' => '0',
            'SUM_CPU_TIME' => '0',
            'MAX_CONTROLLED_MEMORY' => '1198560',
            'MAX_TOTAL_MEMORY' => '1952546',
            'COUNT_SECONDARY' => '0',
            'FIRST_SEEN' => '2023-08-26 09:18:15.533400',
            'LAST_SEEN' => '2023-08-26 09:18:15.576196',
            'QUANTILE_95' => '60255958607',
            'QUANTILE_99' => '60255958607',
            'QUANTILE_999' => '60255958607',
            'QUERY_SAMPLE_TEXT' => "SELECT * FROM table1",
            'QUERY_SAMPLE_SEEN' => '2023-08-26 09:18:15.533400',
            'QUERY_SAMPLE_TIMER_WAIT' => '58424777000',
        ];

        $index_query = $this->createQuery("SELECT * FROM table1", $schema);
        $index_query->setEventsStatementsSummary(EventsStatementsSummary::createFromPerformanceSchema($template));

        $bad_examine_ratio_summary = $template;
        $bad_examine_ratio_summary['SUM_ROWS_SENT'] = 1;
        $bad_examine_ratio_query = $this->createQuery("SELECT * FROM table1", $schema);
        $bad_examine_ratio_query->setEventsStatementsSummary(EventsStatementsSummary::createFromPerformanceSchema($bad_examine_ratio_summary));

        $no_index_query_summary = $template;
        $no_index_query_summary['SUM_NO_INDEX_USED'] = 100;
        $no_index_query = $this->createQuery("SELECT * FROM table1", $schema);
        $no_index_query->setEventsStatementsSummary(EventsStatementsSummary::createFromPerformanceSchema($no_index_query_summary));

        $full_scan_summary = $template;
        $full_scan_summary['SUM_SELECT_SCAN'] = 100;
        $full_scan_query = $this->createQuery("SELECT * FROM table1", $schema);
        $full_scan_query->setEventsStatementsSummary(EventsStatementsSummary::createFromPerformanceSchema($full_scan_summary));

        $no_good_index_summary = $template;
        $no_good_index_summary['SUM_NO_GOOD_INDEX_USED'] = 20;
        $no_good_index_query = $this->createQuery("SELECT * FROM table1", $schema);
        $no_good_index_query->setEventsStatementsSummary(EventsStatementsSummary::createFromPerformanceSchema($no_good_index_summary));

        $sum_sort_scan_summary = $template;
        $sum_sort_scan_summary['SUM_SORT_SCAN'] = 20;
        $sum_sort_scan_query = $this->createQuery("SELECT * FROM table1", $schema);
        $sum_sort_scan_query->setEventsStatementsSummary(EventsStatementsSummary::createFromPerformanceSchema($sum_sort_scan_summary));

        $tmp_disk_summary = $template;
        $tmp_disk_summary['SUM_CREATED_TMP_DISK_TABLES'] = 100;
        $tmp_disk_query = $this->createQuery("SELECT * FROM table1", $schema);
        $tmp_disk_query->setEventsStatementsSummary(EventsStatementsSummary::createFromPerformanceSchema($tmp_disk_summary));

        return [
            [
                $index_query,
                true,
                Report::STATUS_OK
            ],
            [
                $bad_examine_ratio_query,
                true,
                Report::STATUS_CONCERN
            ],
            [
                $full_scan_query,
                true,
                Report::STATUS_WARNING
            ],
            [
                $no_index_query,
                true,
                Report::STATUS_WARNING
            ],
            [
                $no_good_index_query,
                true,
                Report::STATUS_WARNING
            ],
            [
                $sum_sort_scan_query,
                true,
                Report::STATUS_WARNING
            ],
            [
                $tmp_disk_query,
                true,
                Report::STATUS_WARNING
            ],
        ];
    }

    /**
     * @dataProvider providerQueryData
     */
    public function testRun($query, $isSupported, $status) {
        $check = new Inefficient();

        $this->assertEquals(
            $isSupported,
            $check->supports($query),
            "Ensure that we care about only relevant accounts accounts."
        );

        $this->assertEquals(
            $status,
            $check->run($query)->getStatus(),
            "Correctly identifies if there is an issue."
        );
    }
}
