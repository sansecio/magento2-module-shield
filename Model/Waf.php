<?php

namespace Sansec\Shield\Model;

use Magento\Framework\App\RequestInterface;

class Waf
{
    /** @var Rule[] */
    private $rules;

    /** @var array<string, string> */
    private array $ips;

    /** @var array<string, string> */
    private array $networks;

    public function __construct(
        array $rules,
        array $sources = []
    ) {
        $this->rules = $rules;
        $this->ips = $sources['ip'] ?? [];
        $this->networks = $sources['networks'] ?? [];
    }

    public function matchRequest(RequestInterface $request): array
    {
        return array_values(array_filter(
            $this->rules,
            fn($rule) => $rule->matches($request)
        ));
    }
}
