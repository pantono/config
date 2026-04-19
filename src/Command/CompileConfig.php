<?php

namespace Pantono\Config\Command;

use Symfony\Component\Console\Command\Command;
use Pantono\Config\Config;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;

class CompileConfig extends Command
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
        parent::__construct();

    }

    protected function configure(): void
    {
        $this->setName('config:compile');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->config->getAllowedConfigTypes() as $type) {
            $output->write('Compiling ' . $type);
            $this->config->compileConfig($type);
            $output->writeln('Done');
        }
        return 0;
    }
}
