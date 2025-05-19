<?php

namespace Sansec\Shield\Model;

use Magento\Framework\App\RequestInterface;
use Sansec\Shield\Model\RuleFactory;
use Sansec\Shield\Model\ConditionFactory;

class Waf
{
    /** @var Rule[] */
    private $rules = [];

    public function __construct(Rules $rules, RuleFactory $ruleFactory, ConditionFactory $conditionFactory)
    {
        $ruleConfig = $rules->loadRules();
        foreach ($ruleConfig['rules'] ?? [] as $rule) {
            $conditions = [];
            foreach ($rule['conditions'] ?? [] as $condition) {
                $conditions[] = $conditionFactory->create($condition);
            }
            $this->rules[] = $ruleFactory->create([
                'action' => $rule['action'],
                'conditions' => $conditions,
                'name' => $rule['name'] ?? ''
            ]);
        }
    }

    public function matchRequest(RequestInterface $request): array
    {
        return array_values(array_filter(
            $this->rules,
            function (Rule $rule) use ($request) {
                return $rule->matches($request);
            }
        ));
    }
}
