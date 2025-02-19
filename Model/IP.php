<?php

namespace Sansec\Shield\Model;

class IP
{
    /** @var string[]|null */
    private $requestIPs;

    /** @var string[] */
    private $ipHeaders;

    public function __construct(array $ipHeaders = [])
    {
        $this->ipHeaders = $ipHeaders;
    }

    private function isPrivateIP(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    public function collectRequestIPs(): array
    {
        if ($this->requestIPs === null) {
            $requestIPs = [];
            foreach ($this->ipHeaders as $header) {
                if (!isset($_SERVER[$header])) {
                    continue;
                }
                foreach (preg_split('/[\s,]+/', $_SERVER[$header]) as $ip) {
                    $ip = trim($ip);
                    if (empty($ip) || $this->isPrivateIP($ip)) {
                        continue;
                    }
                    $requestIPs[] = $ip;
                }
            }
            $this->requestIPs = array_unique($requestIPs);
        }
        return $this->requestIPs;
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
