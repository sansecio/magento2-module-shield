<?php

namespace Sansec\Shield\Model;

class IP
{
    private const IPV4_MAPPED_PREFIX = "\0\0\0\0\0\0\0\0\0\0\xff\xff";

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

    private function isIPv4Mapped(string $packed): bool
    {
        return strlen($packed) === 16 && strncmp($packed, self::IPV4_MAPPED_PREFIX, 12) === 0;
    }

    private function unwrapIPv4Mapped(string $packed): string
    {
        return $this->isIPv4Mapped($packed) ? substr($packed, 12) : $packed;
    }

    public function normalizeIP(string $ip): ?string
    {
        $ip = trim($ip);
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return inet_ntop($this->unwrapIPv4Mapped(inet_pton($ip)));
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
                    $ip = $this->normalizeIP($ip);
                    if ($ip === null || $this->isPrivateIP($ip)) {
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

        $ip = $this->unwrapIPv4Mapped(inet_pton($ip));
        $subnet = inet_pton($subnet);

        $bits = (int) $bits;
        if ($bits > strlen($subnet) * 8) {
            return false;
        }
        if ($bits >= 96 && $this->isIPv4Mapped($subnet)) {
            $subnet = substr($subnet, 12);
            $bits -= 96;
        }
        if (strlen($ip) !== strlen($subnet)) {
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
