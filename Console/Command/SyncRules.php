<?php

namespace Sansec\Shield\Console\Command;

use Sansec\Shield\Model\Config;
use Sansec\Shield\Model\Rules;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncRules extends Command
{
    /** @var Rules */
    private $rules;

    /** @var Config */
    private $config;

    public function __construct(Rules $rules, Config $config, ?string $name = null)
    {
        parent::__construct($name);
        $this->rules = $rules;
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this->setName('sansec:shield:sync-rules');
        $this->setDescription('Synchronize rules for Sansec Shield');
        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->config->isEnabled()) {
            $output->writeln("Please enable the module and configure the license key.");
            return;
        }

        $output->writeln('Synchronizing rules...');
        try {
            $rules = $this->rules->syncRules();
            $output->writeln(sprintf("Finished synchronization of %d rules.", count($rules['rules'])));
        } catch (\Exception $e) {
            $output->writeln($e->getMessage());
        }
        return 0;
    }
}
