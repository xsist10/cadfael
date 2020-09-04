<?php

declare(strict_types=1);

namespace Cadfael\Cli\Command;

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
use Cadfael\Engine\Factory;
use Cadfael\Engine\Orchestrator;
use Cadfael\Engine\Report;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class RunCommand extends Command
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
        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('Run a collection of checks against a database.')

            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'The host of the database.', 'localhost')
            ->addOption('port', 'p', InputOption::VALUE_REQUIRED, 'The port of the database.', 3306)
            ->addOption('username', 'u', InputOption::VALUE_REQUIRED, 'The username of the database.', 'root')
            ->addArgument('schema', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'The schema to scan.')
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
     * @param string $schemaName
     * @param Factory $factory
     * @param OutputInterface $output
     * @throws \Cadfael\Engine\Exception\MissingPermissions
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function runChecksAgainstSchema(string $schemaName, Factory $factory, OutputInterface $output): void
    {
        // outputs multiple lines to the console (adding "\n" at the end of each line)
        $output->writeln("Attempting to scan schema <info>$schemaName </info>");

        $table = new Table($output);
        $table->setHeaders(['Check', 'Entity', 'Status', 'Message']);
        $table->setColumnMaxWidth(0, 22);
        $table->setColumnMaxWidth(1, 40);
        $table->setColumnMaxWidth(2, 8);
        $table->setColumnMaxWidth(3, 82);

        $tables = $factory->getTables($schemaName);
        if (!count($tables)) {
            $output->writeln('No tables found in this database.');
            return;
        }

        $schema = $tables[0]->getSchema();
        $output->writeln('<info>MySQL Version:</info> ' . $schema->getVersion());
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

        $host = $input->getOption('host') . ':' . $input->getOption('port');
        $schemaList = [];
        if (is_string($input->getArgument('schema'))) {
            $schemaList[] = $input->getArgument('schema');
        } elseif (is_array($input->getArgument('schema'))) {
            $schemaList = (array)$input->getArgument('schema');
        }

        $output->writeln('<info>Host:</info> ' . $host);
        $output->writeln('<info>User:</info> ' . $input->getOption('username'));
        $output->writeln('');

        $question = new Question('What is the database password? ');
        $question->setHidden(true);
        $question->setHiddenFallback(false);

        $helper = $this->getHelper('question');
        $password = $helper->ask($input, $output, $question);
        $output->writeln('');

        foreach ($schemaList as $schemaName) {
            $connectionParams = array(
                'dbname'    => $schemaName,
                'user'      => $input->getOption('username'),
                'password'  => $password,
                'host'      => $host,
                'driver'    => 'pdo_mysql',
            );
            $connection = DriverManager::getConnection($connectionParams);
            $factory = new Factory($connection);

            $log = new Logger('name');
            if ($input->getOption('verbose')) {
                $log->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
            }
            $factory->setLogger($log);


            $this->runChecksAgainstSchema($schemaName, $factory, $output);
        }
        return Command::SUCCESS;
    }
}
