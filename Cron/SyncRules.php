<?php

namespace Sansec\Shield\Cron;

use Sansec\Shield\Logger\Logger;
use Sansec\Shield\Model\Rules;

class SyncRules
{
    /** @var Rules */
    private $rules;

    /** @var Logger */
    private $logger;

    public function __construct(Rules $rules, Logger $logger)
    {
        $this->rules = $rules;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        try {
            $rules = $this->rules->syncRules();
            $this->logger->info(sprintf("Finished synchronization of %d rules.", count($rules['rules'])));
        } catch (\Exception $e) {
            $this->logger->error(sprintf("Failed synchronizing rules: %s", $e->getMessage()));
        }
    }
}
