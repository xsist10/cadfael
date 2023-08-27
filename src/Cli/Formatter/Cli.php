<?php

namespace Cadfael\Cli\Formatter;

use Cadfael\Cli\Formatter;
use Cadfael\Engine\Orchestrator;
use Cadfael\Engine\Report;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

class Cli extends Formatter
{
    protected OutputInterface $output;
    protected int $reportCount = 0;

    protected const TEST_BLOCK_WIDTH = 50;

    private const STATUS_COLOUR = [
        1 => '<fg=green>',
        2 => '<fg=blue>',
        3 => '<fg=cyan>',
        4 => '<fg=yellow>',
        5 => '<fg=red>',
    ];

    protected function renderStatus(int $status): string
    {
        return self::STATUS_COLOUR[$status]. Report::getStatusLabelFromValue($status) . "</>";
    }

    protected function renderStatusLegend(int $status): string
    {
        $legend = [
            Report::STATUS_OK       => '.',
            Report::STATUS_INFO     => 'i',
            Report::STATUS_CONCERN  => 'o',
            Report::STATUS_WARNING  => 'w',
            Report::STATUS_CRITICAL => 'c',
        ];
        return self::STATUS_COLOUR[$status]. $legend[$status] . "</>";
    }

    /**
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;

        $outputStyle = new OutputFormatterStyle('white', null, ['bold']);
        $this->output->getFormatter()->setStyle('title', $outputStyle);
    }

    /**
     * Forces a line break
     *
     * @return Formatter
     */
    public function eol(): Formatter
    {
        $this->output->writeln('');
        return $this;
    }

    /**
     * Wrapper to OutputInterface::write
     *
     * @param string $messages The message as an iterable of strings or a single string
     * @return Formatter
     */
    public function write($messages): Formatter
    {
        $this->output->write($messages);
        return $this;
    }

    /**
     * Wrapper to OutputInterface::error
     *
     * @param string $messages The message as an iterable of strings or a single string
     * @return Formatter
     */
    public function error(string $messages): Formatter
    {
        $this->output->write('<error>' . $messages . '</error>');
        return $this;
    }

    public function renderReports(int $severity, array $reports)
    {
        $report_count = count($reports);
        // Bail out early if we have no reports to report on
        if (!$report_count) {
            $this->write("No reports generated.")->eol();
            return $this;
        }

        $issues = 0;
        $grouped = [];
        $levels = [];
        foreach ($reports as $report) {
            if (!isset($levels[$report->getStatus()])) {
                $levels[$report->getStatus()] = 0;
            }
            $levels[$report->getStatus()]++;
            if ($report->getStatus() != Report::STATUS_OK) {
                $issues++;
            }
            if ($report->getStatus() >= $severity) {
                $grouped[$report->getCheckLabel()][] = $report;
            }
        }

        $this->eol()
            ->write(sprintf(
                "<info>Checks passed:</info> %d/%s",
                $report_count - $issues,
                $report_count
            ))
            ->eol();

        ksort($levels);
        $summary = [];
        foreach ($levels as $level => $count) {
            $summary[] = sprintf(
                "(%s) %s: %d",
                $this->renderStatusLegend($level),
                $this->renderStatus($level),
                $count
            );
        }
        $this->write(implode(', ', $summary))->eol();

        $this->eol()
            ->write(sprintf(
                "<info>Showing:</info> %s and higher",
                Report::getStatusLabelFromValue($severity)
            ))
            ->eol()->eol();

        ksort($grouped);

        foreach ($grouped as $reports) {
            $this->renderGroupedReports($reports);
        }

        return $this;
    }

    protected function renderGroupedReports(array $reports)
    {
        $table = new Table($this->output);
        $table->setHeaders(['Entity', 'Status', 'Message']);

        $check = $reports[0]->getCheck();

        $this->write('<title>> ' . $check->getName() . '</title>')->eol();
        $this->eol();
        if ($check->getDescription()) {
            $this->write('<info>Description:</info> ' . $check->getDescription())->eol();
        }
        if ($check->getReferenceUri()) {
            $this->write('<info>Reference:</info> <href=' . $check->getReferenceUri() . '>'
                . $check->getReferenceUri() . '</>')->eol();
        }
        $this->eol();
        foreach ($reports as $report) {
            $table->addRow([
                strlen($report->getEntity()) > 80 ? wordwrap($report->getEntity(), 80) : $report->getEntity(),
                $this->renderStatus($report->getStatus()),
                implode("\n", $report->getMessages())
            ]);
        }
        $table->render();
        $this->eol();
    }

    public function prepareCallback(Orchestrator $orchestrator)
    {
        // Add callbacks to handle the rendering
        $formatter = $this;
        $orchestrator->addCallbacks(function (Report $report) use ($formatter) {
            if ($formatter->getReportCount() % $formatter::TEST_BLOCK_WIDTH === 0) {
                $formatter->eol();
            }
            $formatter->write($formatter->renderStatusLegend($report->getStatus()));
            $formatter->inceaseReportCount();
        });
    }

    /**
     * @return int
     */
    public function getReportCount(): int
    {
        return $this->reportCount;
    }

    public function inceaseReportCount(): void
    {
        $this->reportCount++;
    }
}
