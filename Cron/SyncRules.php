<?php

namespace Sansec\Shield\Cron;

use Sansec\Shield\Model\Rules;

class SyncRules
{
    private Rules $rules;

    public function __construct(Rules $rules)
    {
        $this->rules = $rules;
    }

    public function execute(): void
    {
        $this->rules->syncRules();
    }
}
