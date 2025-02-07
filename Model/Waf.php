<?php

namespace Sansec\Shield\Model;

use Magento\Framework\App\RequestInterface;

class Waf
{
    /** @var Rule[] */
    private $rules;

    public function __construct(array $rules)
    {
        $this->rules = $rules;
    }

    public function matchRequest(RequestInterface $request): array
    {
        return array_values(array_filter(
            $this->rules,
            fn($rule) => $rule->matches($request)
        ));
    }
}
