<?php

namespace Sansec\Shield\Model;

use Magento\Framework\App\RequestInterface;
use Sansec\Shield\Model\RuleFactory;

class Waf
{
    /** @var Rule[] */
    private $rules;

    public function __construct(Rules $rules, RuleFactory $ruleFactory)
    {
        $ruleConfig = $rules->loadRules();
        foreach ($ruleConfig['rules'] ?? [] as $rule) {
            $this->rules[] = $ruleFactory->create($rule);
        }
    }

    public function matchRequest(RequestInterface $request): array
    {
        return array_values(array_filter(
            $this->rules,
            function(Rule $rule) use ($request) {
                return $rule->matches($request);
            }
        ));
    }
}
