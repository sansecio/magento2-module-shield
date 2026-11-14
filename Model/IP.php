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

    public function getRemoteAddr(): ?string
    {
        $remote = isset($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '';
        return $remote === '' ? null : $remote;
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
            $this->requestIPs = array_values(array_unique($requestIPs));
        }
        return $this->requestIPs;
    }

    public function ipMatchesCidr(string $ip, string $cidr): bool
    {
        $parts = array_map('trim', explode('/', trim($cidr)));
        if (count($parts) !== 2 || !preg_match('/^\d+\z/', $parts[1])) {
            return false;
        }
        [$subnet, $bits] = $parts;

        if (filter_var($ip, FILTER_VALIDATE_IP) === false || filter_var($subnet, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $ip = inet_pton($ip);
        $subnet = inet_pton($subnet);
        if (strlen($ip) !== strlen($subnet)) {
            return false;
        }

        $bits = (int) $bits;
        if ($bits > strlen($ip) * 8) {
            return false;
        }

        $bytes = intdiv($bits, 8);
        if (substr($ip, 0, $bytes) !== substr($subnet, 0, $bytes)) {
            return false;
        }

        $remainder = $bits % 8;
        if ($remainder === 0) {
            return true;
        }

        $mask = (0xff << (8 - $remainder)) & 0xff;
        return (ord($ip[$bytes]) & $mask) === (ord($subnet[$bytes]) & $mask);
    }
}
