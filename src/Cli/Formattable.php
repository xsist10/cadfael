<?php

declare(strict_types=1);


namespace Cadfael\Cli;

use Cadfael\Cli\Formatter\Cli;
use Cadfael\Cli\Formatter\Json;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

trait Formattable
{
    protected Formatter $formatter;

    public function setupFormatArguments(): Command
    {
        $this->addOption(
            'output-format',
            'o',
            InputOption::VALUE_REQUIRED,
            'Changes the output format (json or cli)',
            'cli'
        );

        return $this;
    }

    /**
     * @param OutputInterface $output
     * @param InputInterface $input
     * @return void
     */
    public function setupFormatter(OutputInterface $output, InputInterface $input): void
    {
        $this->formatter = new Cli($output);
        if ($input->getOption('output-format') === 'json') {
            $this->formatter = new Json($output);
        }
    }
}
