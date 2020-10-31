<?php

declare(strict_types=1);

namespace Cadfael\Cli\Command;

use Cadfael\Engine\Check\Column\CorrectUtf8Encoding;
use Cadfael\Engine\Check\Column\ReservedKeywords;
use Cadfael\Engine\Check\Column\SaneAutoIncrement;
use Cadfael\Engine\Check\Schema\AccountsNotProperlyClosingConnections;
use Cadfael\Engine\Check\Schema\UnsupportedVersion;
use Cadfael\Engine\Check\Table\AutoIncrementCapacity;
use Cadfael\Engine\Check\Table\EmptyTable;
use Cadfael\Engine\Check\Table\MustHavePrimaryKey;
use Cadfael\Engine\Check\Table\PreferredEngine;
use Cadfael\Engine\Check\Table\RedundantIndexes;
use Cadfael\Engine\Check\Table\SaneInnoDbPrimaryKey;
use Cadfael\Engine\Factory;
use Cadfael\Engine\Orchestrator;
use Cadfael\Engine\Report;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class RunCommand extends AbstractDatabaseCommand
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'run';

    const STATUS_COLOUR = [
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

    protected function configure(): void
    {
        parent::configure();

        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('Run a collection of checks against a database.')
            ->addOption(
                'performance_schema',
                null,
                InputOption::VALUE_NONE,
                'Include performance_schema metric checks. Only useful if the database has been running for a while.'
            )
            // the full command description shown when running the command with
            // the "--help" option
//            ->setHelp('.')
        ;
    }

    protected function addReportToTable(?Report $report, Table $table): void
    {
        if (!is_null($report) && $report->getStatus() != Report::STATUS_OK) {
            $table->addRow([
                $report->getCheckLabel(),
                $report->getEntity(),
                $this->renderStatus($report),
                implode("\n", $report->getMessages())
            ]);
        }
    }

    /**
     * @param InputInterface $input
     * @param string $schemaName
     * @param Factory $factory
     * @param OutputInterface $output
     * @throws \Cadfael\Engine\Exception\MissingPermissions
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function runChecksAgainstSchema(
        InputInterface $input,
        string $schemaName,
        Factory $factory,
        OutputInterface $output
    ): void {
        // outputs multiple lines to the console (adding "\n" at the end of each line)
        $output->writeln("Attempting to scan schema <info>$schemaName </info>");

        $table = new Table($output);
        $table->setHeaders(['Check', 'Entity', 'Status', 'Message']);
        $table->setColumnMaxWidth(0, 22);
        $table->setColumnMaxWidth(1, 40);
        $table->setColumnMaxWidth(2, 8);
        $table->setColumnMaxWidth(3, 82);

        $database = $factory->buildDatabase([$schemaName]);
        $schema = $database->getSchemas()[0];
        $tables = $schema->getTables();
        if (!count($tables)) {
            $output->writeln('No tables found in this database.');
            return;
        }

        $uptime = (int)$database->getStatus()['Uptime'];
        $output->writeln('<info>MySQL Version:</info> ' . $database->getVersion());
        $output->writeln('<info>Uptime:</info> ' . round($uptime / 60) . ' min');
        $output->writeln('<info>Tables Found:</info> ' . count($tables));
        $output->writeln('');

        $orchestrator = new Orchestrator();
        $orchestrator->addChecks(
            new MustHavePrimaryKey(),
            new SaneInnoDbPrimaryKey(),
            new EmptyTable(),
            new AutoIncrementCapacity(),
            new RedundantIndexes(),
            new ReservedKeywords(),
            new SaneAutoIncrement(),
            new CorrectUtf8Encoding(),
            new PreferredEngine(),
            new UnsupportedVersion()
        );

        if ($input->getOption('performance_schema')) {
            if ($uptime < 86400) {
                $output->writeln('<comment>Server has been running for less than 24 hours.</comment>');
                $output->writeln('<comment>Certain results may be incomplete.</comment>');

                $question = new ConfirmationQuestion(
                    'Continue anyway [Y/N]? ',
                    false
                );

                $helper = $this->getHelper('question');
                if (!$helper->ask($input, $output, $question)) {
                    return;
                }
                $output->writeln('');
            }

            $orchestrator->addChecks(
                new AccountsNotProperlyClosingConnections()
            );
        }

        $orchestrator->addEntities($database);
        $orchestrator->addEntities($schema);
        $orchestrator->addEntities(...$tables);
        foreach ($tables as $entity) {
            $orchestrator->addEntities(...$entity->getColumns());
        }

        $reports = $orchestrator->run();
        foreach ($reports as $report) {
            $this->addReportToTable($report, $table);
        }

        $table->render();
        $output->writeln('');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Cadfael CLI Tool');
        $output->writeln('');

        $this->displayDatabaseDetails($input, $output);
        $password = $this->getDatabasePassword($input, $output);

        foreach ($input->getArgument('schema') as $schemaName) {
            $factory = $this->getFactory($input, $schemaName, $password);
            $this->runChecksAgainstSchema($input, $schemaName, $factory, $output);
            $factory->getConnection()->close();
        }
        return Command::SUCCESS;
    }
}
