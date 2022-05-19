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

    protected function renderStatus(Report $report): string
    {
        return self::STATUS_COLOUR[$report->getStatus()]. $report->getStatusLabel() . "</>";
    }

    protected function renderStatusLegend(Report $report): string
    {
        $legend = [
            Report::STATUS_OK       => '.',
            Report::STATUS_INFO     => 'i',
            Report::STATUS_CONCERN  => 'o',
            Report::STATUS_WARNING  => 'w',
            Report::STATUS_CRITICAL => 'c',
        ];
        return self::STATUS_COLOUR[$report->getStatus()]. $legend[$report->getStatus()] . "</>";
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

    public function renderGroupedReports(array $grouped)
    {
        foreach ($grouped as $reports) {
            $this->renderReports($reports);
        }
    }

    protected function renderReports(array $reports)
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
                $report->getEntity(),
                $this->renderStatus($report),
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
            $formatter->write($formatter->renderStatusLegend($report));
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
