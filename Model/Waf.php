<?php

namespace Sansec\Shield\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Sansec\Shield\Model\RuleFactory;

class Waf
{
    private RuleFactory $ruleFactory;

    private RemoteAddress $remoteAddress;

    /** @var Rule[] */
    private $rules;

    /** @var array<string, string> */
    private array $ips;

    /** @var array<string, string> */
    private array $networks;

    public function __construct(
        RuleFactory $ruleFactory,
        RemoteAddress $remoteAddress,
        array $config
    ) {
        $this->ruleFactory = $ruleFactory;
        $this->remoteAddress = $remoteAddress;
        $this->rules = array_map(fn(array $r): Rule => $ruleFactory->create(['data' => $r]), $config['rules'] ?? []);
        $this->ips = $config['sources']['ips'] ?? [];
        $this->networks = $config['sources']['networks'] ?? [];
    }

    private function ipMatchesCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);

        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;

        return ($ip & $mask) === $subnet;
    }

    private function matchIP(): ?Rule
    {
        $ip = $this->remoteAddress->getRemoteAddress();
        foreach ($this->networks as $cidr => $action) {
            if ($this->ipMatchesCidr($ip, $cidr)) {
                return $this->ruleFactory->create(['data' => ['action' => $action]]);
            }
        }
        if (isset($this->ips[$ip])) {
            return $this->ruleFactory->create(['data' => ['action' => $action]]);
        }
        return null;
    }

    public function matchRequest(RequestInterface $request): array
    {
        $ipMatch = $this->matchIP();
        if ($ipMatch !== null) {
            return [$ipMatch];
        }

        return array_values(array_filter(
            $this->rules,
            fn($rule) => $rule->matches($request)
        ));
    }
}
