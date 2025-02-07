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
            $this->rules->syncRules();
        } catch (\Exception $e) {
            $this->logger->error(sprintf("Failed synchronizing rules: %s", $e->getMessage()));
        }
    }
}
