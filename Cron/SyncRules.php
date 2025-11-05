<?php

namespace Sansec\Shield\Cron;

use Psr\Log\LoggerInterface as Logger;
use Sansec\Shield\Model\Config;
use Sansec\Shield\Model\Rules;

class SyncRules
{
    /** @var Rules */
    private $rules;

    /** @var Logger */
    private $logger;

    /** @var Config */
    private $config;

    public function __construct(Rules $rules, Logger $logger, Config $config)
    {
        $this->rules = $rules;
        $this->logger = $logger;
        $this->config = $config;
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        sleep(rand(0, 20));
        try {
            $rules = $this->rules->syncRules();
            $this->logger->info(sprintf("Finished synchronization of %d rules.", count($rules['rules'])));
        } catch (\Exception $e) {
            $this->logger->error(sprintf("Failed synchronizing rules: %s", $e->getMessage()));
        }
    }
}
