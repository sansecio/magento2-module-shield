<?php

namespace Sansec\Shield\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncRules extends Command
{
    protected function configure(): void
    {
        $this->setName('sansec:shield:sync-rules');
        $this->setDescription('Synchronize rules for Sansec Shield');
        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Synchronizing rules...');
        return 0;
    }
}
