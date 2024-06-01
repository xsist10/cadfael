<?php

declare(strict_types=1);

namespace Cadfael\Cli\Command;

use Cadfael\Cli\Formattable;
use Cadfael\Cli\Formatter\Cli;
use Cadfael\Cli\Formatter\Json;
use Cadfael\Engine\Check\Column\CorrectUtf8Encoding;
use Cadfael\Engine\Check\Column\ReservedKeywords;
use Cadfael\Engine\Check\Column\SaneAutoIncrement;
use Cadfael\Engine\Check\Column\UUIDStorage;
use Cadfael\Engine\Check\Index\IndexPrefix;
use Cadfael\Engine\Check\Database\RequirePrimaryKey;
use Cadfael\Engine\Check\Table\AutoIncrementCapacity;
use Cadfael\Engine\Check\Table\EmptyTable;
use Cadfael\Engine\Check\Table\MustHavePrimaryKey;
use Cadfael\Engine\Check\Table\PreferredEngine;
use Cadfael\Engine\Check\Table\RedundantIndexes;
use Cadfael\Engine\Check\Table\SaneInnoDbPrimaryKey;
use Cadfael\Engine\Entity\Schema;
use Cadfael\Engine\Exception\InvalidColumn;
use Cadfael\Engine\Factory\Queries;
use Cadfael\Engine\Orchestrator;
use Cadfael\Engine\Report;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToWriteFile;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RunStatementCommand extends Command
{
    use Formattable;
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'run-statement';

    protected int $worstReportStatus = Report::STATUS_OK;

    /**
     * At what level do we return a failed run (for use in bash/CI)
     * @var int
     */
    protected int $failedStatusReport = Report::STATUS_WARNING;

    /**
     * @var array<Report>
     */
    public array $reports = [];

    protected function configure(): void
    {
        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('Run a collection of checks against a SQL DDL file.')
            ->addArgument(
                'file',
                InputArgument::REQUIRED,
                'The file containing the SQL DDL statements.'
            )
            ->setupFormatArguments()
            ->addOption(
                'severity',
                null,
                InputOption::VALUE_REQUIRED,
                'Specify the level of checks to show (1-5). 1 will show all reports, 5 will only show the '
                . 'most critical. Has no effect if output is not cli.',
                Report::STATUS_CONCERN
            )
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp("")
        ;
    }

    /**
     * @param InputInterface $input
     * @param array<Schema> $schemas
     * @throws \Exception
     */
    protected function runChecksAgainstSchema(
        InputInterface $input,
        array $schemas
    ): void {

        $this->formatter->eol();

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
            new RequirePrimaryKey(),
            new IndexPrefix(),
            new UUIDStorage(),
        );

        foreach ($schemas as $schema) {
            $tables = $schema->getTables();
            $this->formatter->eol();
            $this->formatter
                ->write("<comment>!!! This is an experimental feature !!!</comment>")->eol()
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->formatter = new Cli($output);
        if ($input->getOption('output-format') === 'json') {
            $this->formatter = new Json($output);
        }

        $title = $this->getApplication()->getLongVersion();
        $this->formatter->write($title)->eol();
        $this->formatter->eol();

        // Check if the user has specified a SQL DDL file
        if (!$input->getArgument('file')) {
            $this->formatter->write('<error>No SQL DDL file specified.</error>')->eol()->eol();
            return Command::FAILURE;
        }

        try {
            $adapter = new LocalFilesystemAdapter(getcwd());
            $filesystem = new Filesystem($adapter);
            $filename = $input->getArgument('file');
            $content = $filesystem->read($filename);
        } catch (FilesystemException|UnableToWriteFile $exception) {
            $this->formatter->write('<error>' . $exception->getMessage() . '</error>')->eol()->eol();
            return Command::FAILURE;
        }

        $queries = new Queries("8.1.0", $content);
        $log = new Logger('name');
        if ($input->getOption('verbose')) {
            $log->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }
        $queries->setLogger($log);

        try {
            $schemas = $queries->processIntoSchemas();
        } catch (InvalidColumn $exception) {
            $this->formatter->write('<error>' . $exception->getMessage() . '</error>')->eol()->eol();
            return Command::FAILURE;
        }

        if (!count($schemas)) {
            $this->formatter->write('<error>No schemas specified.</error>')->eol()->eol();
            return Command::FAILURE;
        }

        try {
            $this->runChecksAgainstSchema($input, $schemas);
        } catch (InvalidColumn $exception) {
            $this->formatter->error($exception->getMessage());
        }

        // If we get anything serious, our script should fail
        if ($this->worstReportStatus >= $this->failedStatusReport) {
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }
}
