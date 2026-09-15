<?php

namespace Sansec\Shield\Model\Report;

use Sansec\Shield\Model\Report;

class Consumer
{
    /** @var Report */
    private $report;

    public function __construct(Report $report)
    {
        $this->report = $report;
    }

    public function process(string $payload)
    {
        $this->report->sendPayload($payload);
    }
}
