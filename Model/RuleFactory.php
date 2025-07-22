<?php

namespace Sansec\Shield\Model;

use Sansec\Shield\Logger\Logger;

class RuleFactory
{
    /** @var IP */
    private $ip;

    /** @var Logger */
    private $logger;

    public function __construct(IP $ip, Logger $logger)
    {
        $this->ip = $ip;
        $this->logger = $logger;
    }

    /**
     * @param array $data
     * @return Rule
     */
    public function create(array $data = [])
    {
        return new Rule(
            $this->ip,
            $this->logger,
            $data['action'],
            $data['conditions'] ?? []
        );
    }
}
