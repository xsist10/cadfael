<?php

namespace Cadfael\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AboutCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'about';

    protected function configure(): void
    {
        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('About the Cadfael CLI tool.')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('This command provides a detailed explanation of what the Cadfael tool is for.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // outputs multiple lines to the console (adding "\n" at the end of each line)
        $output->writeln("About Information");

        return Command::SUCCESS;
    }
}
