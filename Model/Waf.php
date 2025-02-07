<?php

namespace Sansec\Shield\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Sansec\Shield\Model\RuleFactory;

class Waf
{
    /** @var RuleFactory */
    private $ruleFactory;

    /** @var Rule[] */
    private $rules;

    /** @var array<string, string> */
    private $ips;

    /** @var array<string, string> */
    private $networks;

    public function __construct(Rules $rules, RuleFactory $ruleFactory)
    {
        $this->ruleFactory = $ruleFactory;
        $config = $rules->loadRules();
        $this->rules = array_map(function($rule) use ($ruleFactory) {
            return $ruleFactory->create($rule);
        }, $config['rules'] ?? []);
        $this->ips = $config['sources']['ips'] ?? [];
        $this->networks = $config['sources']['networks'] ?? [];
    }

    private function collectIPs(): array
    {
        $requestIPs = [];

        $ipHeaders = [
            'REMOTE_ADDR',
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR'
        ];

        foreach ($ipHeaders as $header) {
            if (isset($_SERVER[$header])) {
                // Split on comma and whitespace, clean up each IP
                $ips = preg_split('/[\s,]+/', $_SERVER[$header]);
                foreach ($ips as $ip) {
                    $ip = trim($ip);
                    if ($ip !== '' && !$this->isPrivateIP($ip)) {
                        $requestIPs[] = $ip;
                    }
                }
            }
        }

        return array_unique($requestIPs);
    }

    private function isPrivateIP(string $ip): bool
    {
        $ip = ip2long($ip);
        if ($ip === false) {
            return true; // Invalid IPs are considered private
        }

        // RFC 1918 private networks
        return (
            ($ip & 0xff000000) === 0x0a000000 || // 10.0.0.0/8
            ($ip & 0xfff00000) === 0xac100000 || // 172.16.0.0/12
            ($ip & 0xffff0000) === 0xc0a80000 || // 192.168.0.0/16
            // RFC 6598 Carrier-grade NAT
            ($ip & 0xff000000) === 0x64400000 || // 100.64.0.0/10
            // RFC 3927 Link-local
            ($ip & 0xffff0000) === 0xa9fe0000 || // 169.254.0.0/16
            // RFC 4193 Unique local addresses
            ($ip & 0xfe000000) === 0xfc000000    // fc00::/7
        );
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
        foreach ($this->collectIPs() as $ip) {
            foreach ($this->networks as $cidr => $action) {
                if ($this->ipMatchesCidr($ip, $cidr)) {
                    return $this->ruleFactory->create(['action' => $action]);
                }
            }
            if (isset($this->ips[$ip])) {
                return $this->ruleFactory->create(['action' => $action]);
            }
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
            function(Rule $rule) use ($request) {
                return $rule->matches($request);
            }
        ));
    }
}
