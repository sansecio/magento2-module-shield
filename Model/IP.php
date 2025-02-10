<?php

namespace Sansec\Shield\Model;

class IP
{
    /** @var string[] */
    private $ipHeaders = [
        'REMOTE_ADDR',
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR'
    ];

    private function isPrivateIP(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    public function collectRequestIPs(): array
    {
        $requestIPs = [];
        foreach ($this->ipHeaders as $header) {
            if (isset($_SERVER[$header])) {
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

    public function ipMatchesCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);

        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;

        return ($ip & $mask) === $subnet;
    }
}
