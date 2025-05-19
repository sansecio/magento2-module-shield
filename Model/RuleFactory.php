<?php

namespace Sansec\Shield\Model;

class RuleFactory
{
    /** @var IP */
    private $ip;

    public function __construct(IP $ip)
    {
        $this->ip = $ip;
    }

    /**
     * @param array $data
     * @return Rule
     */
    public function create(array $data = [])
    {
        return new Rule(
            $this->ip,
            $data['action'],
            $data['conditions'] ?? [],
            $data['name'] ?? ''
        );
    }
}
