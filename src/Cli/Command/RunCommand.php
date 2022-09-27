<?php

declare(strict_types=1);

namespace Cadfael\Cli\Command;

use Cadfael\Cli\Formatter\Cli;
use Cadfael\Cli\Formatter\Json;
use Cadfael\Engine\Check\Account\NotConnecting;
use Cadfael\Engine\Check\Account\NotProperlyClosingConnections;
use Cadfael\Engine\Check\Column\CorrectUtf8Encoding;
use Cadfael\Engine\Check\Column\LowCardinalityExpensiveStorage;
use Cadfael\Engine\Check\Column\ReservedKeywords;
use Cadfael\Engine\Check\Column\SaneAutoIncrement;
use Cadfael\Engine\Check\Column\UUIDStorage;
use Cadfael\Engine\Check\Index\IndexPrefix;
use Cadfael\Engine\Check\Index\LowCardinality;
use Cadfael\Engine\Check\Query\FunctionsOnIndex;
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

    /**
     * @var array<Report>
     */
    public array $reports = [];

    protected function configure(): void
    {
        parent::configure();

        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('Run a collection of checks against a database.')
            ->addOption(
                'performance_schema',
                'ps',
                InputOption::VALUE_NONE,
                'Include performance_schema metric checks. Only useful if the database has been running for a while.'
            )
            ->addOption(
                'output-format',
                'o',
                InputOption::VALUE_REQUIRED,
                'Changes the output format (json or cli)',
                'cli'
            )
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp(
                "You can set the following environment variables:\n" .
                "* MYSQL_DATABASE\n" .
                "* MYSQL_USER\n" .
                "* MYSQL_PASSWORD\n"
            )
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

    public function renderReports(): void
    {
        $report_count = count($this->reports);
        // Bail out early if we have no reports to report on
        if (!$report_count) {
            $this->formatter->write("No reports generated.")->eol();
            return;
        }

        $issues = 0;
        $grouped = [];
        foreach ($this->reports as $report) {
            if ($report->getStatus() !== Report::STATUS_OK) {
                $issues++;
                $grouped[$report->getCheckLabel()][] = $report;
            }
        }

        $this->formatter
            ->eol()
            ->write(sprintf(
                "<info>Checks passed:</info> %d/%s",
                $report_count - $issues,
                $report_count
            ))
            ->eol()
            ->eol();

        ksort($grouped);

        $this->formatter->renderGroupedReports($grouped);
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
        $load_performance_schema = $input->getOption('performance_schema');
        $database = $factory->buildDatabase($factory->getConnection(), $schemaNames, $load_performance_schema);
        $uptime = (int)$database->getStatus()['Uptime'];
        $this->formatter->write('<info>MySQL Version:</info> ' . $database->getVersion())->eol();
        $this->formatter->write('<info>Uptime:</info> ' . $this->returnUptimeInBestUnits($uptime))->eol();

        $orchestrator = new Orchestrator();

        // Add callbacks to handle the rendering
        $this->formatter->prepareCallback($orchestrator);

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
            new UUIDStorage(),
            new LowCardinalityExpensiveStorage(),
        );

        if ($load_performance_schema) {
            $this->formatter->eol();
            $this->formatter->write('Enabling performance_schema checks.')->eol();
            // Either the performance schema isn't enabled
            if (!$database->hasPerformanceSchema()) {
                $this->formatter
                    ->write('<comment>This server does not have the performance_schema enabled.</comment>')
                    ->eol();
                $this->formatter->write('<comment>Disabling the flag and continuing.</comment>')->eol();
                $load_performance_schema = false;
            // Or we don't have access to it
            } elseif (!$factory->hasPermission('performance_schema', '?')) {
                $this->formatter->error('User account does not have permission to query performance_schema.')->eol();
                $this->formatter->write('Try run the command without the --performance_schema flag.')->eol();
                $this->formatter->eol();
                return;
            // Or the server really hasn't been on long enough for good results.
            } elseif ($uptime < 86400) {
                $this->formatter
                    ->write('<comment>This server has been running for less than 24 hours.</comment>')
                    ->eol();
                $this->formatter->write('<comment>Certain checks may be incomplete or misleading.</comment>')->eol();

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
                new Inefficient(),
                new FunctionsOnIndex(),
            );
        }

        $orchestrator->addEntities($database);
        $orchestrator->addEntities(...$database->getAccounts());

        foreach ($database->getSchemas() as $schema) {
            $tables = $schema->getTables();
            $this->formatter->eol();
            $this->formatter
                ->write("Attempting to scan schema <info>" . $schema->getName() . "</info>")
                ->eol();
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

            $this->formatter->eol();
            $this->renderReports();
            $this->formatter->eol();
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param String $password
     * @param array $schemas
     * @return int
     * @throws \Doctrine\DBAL\Driver\Exception
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
            $this->formatter->error($e->getMessage())->eol();
        } catch (MissingInformationSchema $e) {
            $this->formatter->error('Unable to retrieve information for ' . $schemas[0])->eol();
        }

        // If we get anything serious, our script should fail
        if ($this->worstReportStatus >= Report::STATUS_WARNING) {
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->formatter = new Cli($output);
        if ($input->getOption('output-format') === 'json') {
            $this->formatter = new Json($output);
        }
        $this->formatter->write('Cadfael CLI Tool')->eol();
        $this->formatter->eol();

        $this->displayDatabaseDetails($input);
        $password = $this->getDatabasePassword($input, $output);

        $schemas = [];
        if ($input->getArgument('schema')) {
            $schemas = (array)$input->getArgument('schema');
        }
        if (isset($_SERVER['MYSQL_DATABASE'])) {
            $schemas = [ $_SERVER['MYSQL_DATABASE'] ];
        }

        return $this->processSchemas($input, $output, $password, $schemas);
    }
}
