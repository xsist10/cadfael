<?php

namespace Cadfael\Cli;

use Cadfael\Engine\Orchestrator;
use Cadfael\Engine\Report;
use Symfony\Component\Console\Output\OutputInterface;

abstract class Formatter
{
    protected OutputInterface $output;

    /**
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * Abtract function for ending a line
     *
     * @return Formatter
     */
    abstract public function eol(): Formatter;

    /**
     * Abtract function for writing to the screen
     *
     * @param string $messages The message as a single string
     * @return Formatter
     */
    abstract public function write($messages): Formatter;

    /**
     * Output an error to the screen
     *
     * @param string $messages The message as a single string
     * @return Formatter
     */
    abstract public function error(string $messages): Formatter;

    /**
     * @param int $severity
     * @param Report[] $reports
     * @return Formatter
     */
    abstract public function renderReports(int $severity, array $reports);

    abstract public function prepareCallback(Orchestrator $orchestrator);
}
