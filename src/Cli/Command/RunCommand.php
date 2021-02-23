<?php

declare(strict_types=1);

namespace Cadfael\Cli\Command;

use Cadfael\Engine\Check\Account\NotConnecting;
use Cadfael\Engine\Check\Account\NotProperlyClosingConnections;
use Cadfael\Engine\Check\Column\CorrectUtf8Encoding;
use Cadfael\Engine\Check\Column\ReservedKeywords;
use Cadfael\Engine\Check\Column\SaneAutoIncrement;
use Cadfael\Engine\Check\Schema\UnsupportedVersion;
use Cadfael\Engine\Check\Table\AutoIncrementCapacity;
use Cadfael\Engine\Check\Table\EmptyTable;
use Cadfael\Engine\Check\Table\MustHavePrimaryKey;
use Cadfael\Engine\Check\Table\PreferredEngine;
use Cadfael\Engine\Check\Table\RedundantIndexes;
use Cadfael\Engine\Check\Table\SaneInnoDbPrimaryKey;
use Cadfael\Engine\Check\Table\UnusedIndexes;
use Cadfael\Engine\Check\Table\UnusedTable;
use Cadfael\Engine\Factory;
use Cadfael\Engine\Orchestrator;
use Cadfael\Engine\Report;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Cadfael\Engine\Exception\MissingPermissions;
use Doctrine\DBAL\DBALException;
use Cadfael\Engine\Exception\MissingInformationSchema;

class RunCommand extends AbstractDatabaseCommand
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'run';

    protected int $worstReportStatus = Report::STATUS_OK;

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
            if ($report->getStatus() > $this->worstReportStatus) {
                $this->worstReportStatus = $report->getStatus();
            }
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
     * @param array<string> $schemaNames
     * @param Factory $factory
     * @param OutputInterface $output
     * @throws MissingPermissions
     * @throws DBALException
     * @throws MissingInformationSchema
     */
    protected function runChecksAgainstSchema(
        InputInterface $input,
        array $schemaNames,
        Factory $factory,
        OutputInterface $output
    ): void {
        // outputs multiple lines to the console (adding "\n" at the end of each line)

        $database = $factory->buildDatabase($factory->getConnection(), $schemaNames);
        $uptime = (int)$database->getStatus()['Uptime'];
        $output->writeln('<info>MySQL Version:</info> ' . $database->getVersion());
        $output->writeln('<info>Uptime:</info> ' . round($uptime / 60) . ' min');

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

        $load_information_schema = $input->getOption('performance_schema');
        if ($load_information_schema) {
            $output->writeln('');
            $output->writeln('Enabling performance_schema checks.');
            // Either the performance schema isn't enabled
            if (!$database->hasPerformanceSchema()) {
                $output->writeln('<comment>This server does not performance_schema enabled.</comment>');
                $output->writeln('<comment>Disabling the flag and continuing.</comment>');
                $load_information_schema = false;
            // Or we don't have access to it
            } elseif (!$factory->hasPermission('performance_schema', '?')) {
                $output->writeln('<error>User account does not have permission to query performance_schema.</error>');
                $output->writeln('Try run the command without the --performance_schema flag.');
                $output->writeln('');
                return;
            // Or the server really hasn't been on long enough for good results.
            } elseif ($uptime < 86400) {
                $output->writeln('<comment>This server has been running for less than 24 hours.</comment>');
                $output->writeln('<comment>Certain checks may be incomplete or misleading.</comment>');

                $question = new ConfirmationQuestion(
                    'Run with performance schema checks anyway [Y/N]? ',
                    false
                );

                $helper = $this->getHelper('question');
                $load_information_schema = $helper->ask($input, $output, $question);
            }
        }

        if ($load_information_schema) {
            $orchestrator->addChecks(
                new NotProperlyClosingConnections(),
                new UnusedIndexes(),
                new NotConnecting(),
                new UnusedTable()
            );
        }

        foreach ($database->getSchemas() as $schema) {
            $table = new Table($output);
            $table->setHeaders(['Check', 'Entity', 'Status', 'Message']);
            $table->setColumnMaxWidth(0, 22);
            $table->setColumnMaxWidth(1, 40);
            $table->setColumnMaxWidth(2, 8);
            $table->setColumnMaxWidth(3, 82);


            $tables = $schema->getTables();
            $output->writeln('');
            $output->writeln("Attempting to scan schema <info>" . $schema->getName() . "</info>");
            $output->writeln('<info>Tables Found:</info> ' . count($tables));
            $output->writeln('');

            if (!count($tables)) {
                $output->writeln('No tables found in this schema.');
                return;
            }

            $orchestrator->addEntities($database);
            $orchestrator->addEntities(...$database->getAccounts());
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
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Cadfael CLI Tool');
        $output->writeln('');

        $this->displayDatabaseDetails($input, $output);
        $password = $this->getDatabasePassword($input, $output);

        $schemas = $input->getArgument('schema');
        $factory = $this->getFactory($input, $schemas[0], $password);
        $this->runChecksAgainstSchema($input, $schemas, $factory, $output);
        $factory->getConnection()->close();

        // If we get anything serious, our script should fail
        if ($this->worstReportStatus >= Report::STATUS_WARNING) {
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }
}
