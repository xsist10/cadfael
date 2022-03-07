<?php

namespace Cadfael\Cli\Formatter;

use Cadfael\Cli\Formatter;
use Cadfael\Engine\Orchestrator;
use Cadfael\Engine\Report;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

class Json extends Formatter
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
     * noop
     *
     * @return Formatter
     */
    public function eol(): Formatter
    {
        return $this;
    }

    /**
     * noop
     *
     * @param string $messages The message as an iterable of strings or a single string
     * @return Formatter
     */
    public function write($messages): Formatter
    {
        return $this;
    }

    /**
     * Wrapper to hard exit
     *
     * @param string $messages The message as an iterable of strings or a single string
     * @return Formatter
     */
    public function error(string $messages): Formatter
    {
        fwrite(STDERR, "$messages\n");
        return $this;
    }

    public function renderGroupedReports(array $grouped)
    {
        $response = [];
        foreach ($grouped as $reports) {
            $response[] = $this->renderReports($reports);
        }

        $this->output->write(json_encode($response));
    }

    protected function renderReports(array $reports)
    {
        $check = $reports[0]->getCheck();
        $response = [
            'check' => [
                'name' => $check->getName(),
                'description' => $check->getDescription(),
                'reference' => $check->getReferenceUri()
            ],
            'results' => []
        ];

        foreach ($reports as $report) {
            $response['results'][] = [
                'entity' => (string)$report->getEntity(),
                'status' => $report->getStatusLabel(),
                'messages' => $report->getMessages(),
                'data' => $report->getData()
            ];
        }

        return $response;
    }

    public function prepareCallback(Orchestrator $orchestrator)
    {
    }
}
