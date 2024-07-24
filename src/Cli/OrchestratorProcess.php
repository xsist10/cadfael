<?php

declare(strict_types=1);


namespace Cadfael\Cli;

use Cadfael\Engine\Entity\Schema;
use Cadfael\Engine\Orchestrator;
use Cadfael\Engine\Report;
use Exception;
use Symfony\Component\Console\Input\InputInterface;

trait OrchestratorProcess
{
    /**
     * @param Schema $schema
     * @param Orchestrator $orchestrator
     * @param InputInterface $input
     * @return void
     * @throws Exception
     */
    public function runOrchestratorAgainstSchema(Schema $schema, Orchestrator $orchestrator, InputInterface $input): void
    {
        $tables = $schema->getTables();
        $this->formatter->eol();
        $this->formatter
            ->write("Attempting to scan schema <info>" . $schema->getName() . "</info>")->eol();
        $this->formatter->write('<info>Tables Found:</info> ' . count($tables))->eol();

        if (!count($tables)) {
            $this->formatter->write('No tables found in this schema.')->eol();
            return;
        }

        $orchestrator->addEntities($schema);
        $orchestrator->addEntities(...$tables);
        $orchestrator->addEntities(...$schema->getQueries());
        foreach ($tables as $entity) {
            $orchestrator->addEntities(...$entity->getColumns());
            $orchestrator->addEntities(...$entity->getIndexes());
        }

        $this->reports = $orchestrator->run();

        $severity = (int)$input->getOption('severity') ?? Report::STATUS_INFO;
        // We match the failed status to the severity (unless it's OK, then bump it up to INFO at least)
        $this->failedStatusReport = $severity > Report::STATUS_OK ? $severity : Report::STATUS_INFO;

        $this->formatter
            ->eol()
            ->renderReports(
                $severity,
                $this->reports
            )
            ->eol();
    }
}