<?php

declare(strict_types=1);

namespace Cadfael\Cli\Command;

use Cadfael\Cli\Formatter;
use Cadfael\Engine\Factory;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

abstract class AbstractDatabaseCommand extends Command
{
    protected Formatter $formatter;

    /**
     * @param InputInterface $input
     * @return mixed|string
     */
    public function getUsername(InputInterface $input)
    {
        return $input->getOption('username')
            ? $input->getOption('username')
            : $_SERVER['MYSQL_USER'] ?? 'root';
    }

    protected function configure(): void
    {
        $this
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'The host of the database.', 'localhost')
            ->addOption('port', 'p', InputOption::VALUE_REQUIRED, 'The port of the database.', 3306)
            ->addOption(
                'username',
                'u',
                InputOption::VALUE_REQUIRED,
                'The username of the database (or set environment variable MYSQL_USER).'
            )
            ->addOption(
                'secret',
                's',
                InputOption::VALUE_REQUIRED,
                'Specify a file that contains the database password (or set environment variable MYSQL_PASSWORD).'
            )
            ->addArgument(
                'schema',
                InputArgument::IS_ARRAY,
                'The schema(s) to use (or set environment variable MYSQL_DATABASE).'
            );
    }

    protected function displayDatabaseDetails(InputInterface $input): void
    {
        $this->formatter->write(sprintf(
            "<info>Host:</info> %s:%s",
            $input->getOption('host'),
            $input->getOption('port')
        ))->eol();
        $this->formatter->write('<info>User:</info> ' . $this->getUsername($input))->eol();
        $this->formatter->eol();
    }

    protected function getDatabasePassword(InputInterface $input, OutputInterface $output): string
    {
        // First we see if a secret file is provided
        if ($input->getOption('secret')) {
            $file = $input->getOption('secret');
            if (file_exists($file)) {
                $password = trim(file_get_contents($file));
                return $password ?? '';
            }
        }

        // Then we check if there is an environment variables
        if (isset($_SERVER['MYSQL_PASSWORD'])) {
            return $_SERVER['MYSQL_PASSWORD'];
        }

        $question = new Question('What is the database password? ');
        $question->setHidden(true);
        $question->setHiddenFallback(false);

        $helper = $this->getHelper('question');
        $password = $helper->ask($input, $output, $question);
        $output->writeln('');
        return $password ?? '';
    }

    /**
     * @param InputInterface $input
     * @param string $schemaName
     * @param string $password
     * @return Factory
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function getFactory(InputInterface $input, string $schemaName, string $password): Factory
    {
        // Try use a specified username, otherwise default to the environment variables, finally fall back to root
        $username = $this->getUsername($input);

        $connectionParams = array(
            'dbname' => $schemaName,
            'user' => $username,
            'password' => $password,
            'host' => $input->getOption('host') . ':' . $input->getOption('port'),
            'driver' => 'pdo_mysql',
        );
        $connection = DriverManager::getConnection($connectionParams);
        $factory = new Factory($connection);

        $log = new Logger('name');
        if ($input->getOption('verbose')) {
            $log->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }
        $factory->setLogger($log);
        return $factory;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    protected function getLocalStorage(string $name): Connection
    {
        $connectionParams = array(
            'dbname' => $name,
            'driver' => 'pdo_sqlite',
            'path' => $name . '.sqlite',
        );
        return DriverManager::getConnection($connectionParams);
    }
}
