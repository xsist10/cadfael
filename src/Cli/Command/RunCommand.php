<?php

declare(strict_types=1);

namespace Cadfael\Cli\Command;

use Cadfael\Engine\Check\Account\NotConnecting;
use Cadfael\Engine\Check\Account\NotProperlyClosingConnections;
use Cadfael\Engine\Check\Column\CorrectUtf8Encoding;
use Cadfael\Engine\Check\Column\ReservedKeywords;
use Cadfael\Engine\Check\Column\SaneAutoIncrement;
use Cadfael\Engine\Check\Column\UUIDStorage;
use Cadfael\Engine\Check\Index\IndexPrefix;
use Cadfael\Engine\Check\Index\LowCardinality;
use Cadfael\Engine\Check\Query\Inefficient;
use Cadfael\Engine\Check\Database\UnsupportedVersion;
use Cadfael\Engine\Check\Database\RequirePrimaryKey;
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
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
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

    protected const TEST_BLOCK_WIDTH = 60;

    protected int $worstReportStatus = Report::STATUS_OK;

    /**
     * @var array<Report>
     */
    public array $reports = [];

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

    private function returnUptimeInBestUnits(int $uptime_in_seconds): string
    {
        if ($uptime_in_seconds < 60) {
            return $uptime_in_seconds . ' secs';
        } elseif ($uptime_in_seconds < 3600) {
            return round($uptime_in_seconds / 60) . ' mins';
        } elseif ($uptime_in_seconds < 216000) {
            return round($uptime_in_seconds / 3600, 1) . ' hours';
        } else {
            return round($uptime_in_seconds / 216000, 1) . ' days';
        }
    }

    public function addReport(Report $report): void
    {
        $this->reports[] = $report;
    }

    public function renderReports(OutputInterface $output): void
    {
        $issues = 0;
        $grouped = [];
        foreach ($this->reports as $report) {
            if ($report->getStatus() !== Report::STATUS_OK) {
                $issues++;
                $grouped[$report->getCheckLabel()][] = $report;
            }
        }

        $report_count = count($this->reports);
        $output->writeln('');
        $output->writeln('<info>Checks passed:</info> ' . ($report_count - $issues) . "/" . $report_count);
        $output->writeln('');

        ksort($grouped);

        foreach ($grouped as $check_name => $reports) {
            $table = new Table($output);
            $table->setHeaders(['Entity', 'Status', 'Message']);

            $check = $reports[0]->getCheck();

            $output->writeln('<title>> ' . $check->getName() . '</title>');
            $output->writeln('');
            if ($check->getDescription()) {
                $output->writeln('<info>Description:</info> ' . $check->getDescription());
            }
            if ($check->getReferenceUri()) {
                $output->writeln('<info>Reference:</info> <href=' . $check->getReferenceUri() . '>'
                    . $check->getReferenceUri() . '</>');
            }
            $output->writeln('');
            foreach ($reports as $report) {
                $table->addRow([
                    $report->getEntity(),
                    $this->renderStatus($report),
                    implode("\n", $report->getMessages())
                ]);
            }
            $table->render();
            $output->writeln('');
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
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Exception
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
        $output->writeln('<info>Uptime:</info> ' . $this->returnUptimeInBestUnits($uptime));

        $orchestrator = new Orchestrator();

        // Add callbacks to handle the rendering
        $command = $this;
        $orchestrator->addCallbacks(function (Report $report) use ($command, $output) {

            $output->write($command->renderStatusLegend($report));
            $command->addReport($report);
            if (count($command->reports) % $command::TEST_BLOCK_WIDTH === 0) {
                $output->writeln('');
            }
        });

        // Setup the checks we want to perform
        // TODO: Make this configurable so 3rd party checks can be added or only specific subsets are run
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
            new UnsupportedVersion(),
            new RequirePrimaryKey(),
            new LowCardinality(),
            new IndexPrefix(),
            new UUIDStorage()
        );

        $load_performance_schema = $input->getOption('performance_schema');
        if ($load_performance_schema) {
            $output->writeln('');
            $output->writeln('Enabling performance_schema checks.');
            // Either the performance schema isn't enabled
            if (!$database->hasPerformanceSchema()) {
                $output->writeln('<comment>This server does not have the performance_schema enabled.</comment>');
                $output->writeln('<comment>Disabling the flag and continuing.</comment>');
                $load_performance_schema = false;
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
                $load_performance_schema = $helper->ask($input, $output, $question);
            }
        }

        if ($load_performance_schema) {
            $orchestrator->addChecks(
                new NotProperlyClosingConnections(),
                new UnusedIndexes(),
                new NotConnecting(),
                new UnusedTable(),
                new Inefficient()
            );
        }

        foreach ($database->getSchemas() as $schema) {
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
            $orchestrator->addEntities(...$schema->getQueries());
            foreach ($tables as $entity) {
                $orchestrator->addEntities(...$entity->getColumns());
                $orchestrator->addEntities(...$entity->getIndexes());
            }

            $orchestrator->run();

            $output->writeln('');

            $this->renderReports($output);
            $output->writeln('');
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param String $password
     * @param array $schemas
     * @return int
     */
    protected function processSchemas(
        InputInterface $input,
        OutputInterface $output,
        String $password,
        array $schemas
    ): int {
        if (!count($schemas)) {
            return Command::FAILURE;
        }

        try {
            $factory = $this->getFactory($input, $schemas[0], $password);
            $this->runChecksAgainstSchema($input, $schemas, $factory, $output);
            $factory->getConnection()->close();
        } catch (DBALException | MissingPermissions $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
        } catch (MissingInformationSchema $e) {
            $output->writeln('<error>Unable to retrieve information for ' . $schemas[0] . '</error>');
        }

        // If we get anything serious, our script should fail
        if ($this->worstReportStatus >= Report::STATUS_WARNING) {
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Cadfael CLI Tool');
        $output->writeln('');

        $outputStyle = new OutputFormatterStyle('white', null, ['bold']);
        $output->getFormatter()->setStyle('title', $outputStyle);

        $this->displayDatabaseDetails($input, $output);
        $password = $this->getDatabasePassword($input, $output);

        return $this->processSchemas($input, $output, $password, (array)$input->getArgument('schema'));
    }
}
