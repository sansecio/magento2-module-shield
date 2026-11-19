<?php

namespace Sansec\Shield\Test\Model;

use PHPUnit\Framework\TestCase;
use Sansec\Shield\Model\IP;

class IPTest extends TestCase
{
    /** @var array */
    private $serverBackup;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
    }

    public function testGetRemoteAddrReturnsRemoteAddr()
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.42';
        $this->assertSame('203.0.113.42', (new IP())->getRemoteAddr());
    }

    public function testGetRemoteAddrIgnoresForwardedHeaders()
    {
        unset($_SERVER['REMOTE_ADDR']);
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.42';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.42';
        $this->assertNull((new IP())->getRemoteAddr());
    }

    public function testGetRemoteAddrReturnsNullWhenBlank()
    {
        $_SERVER['REMOTE_ADDR'] = "  \t";
        $this->assertNull((new IP())->getRemoteAddr());
    }

    /**
     * @dataProvider cidrProvider
     */
    public function testIpMatchesCidr(string $ip, string $cidr, bool $expected)
    {
        $this->assertSame($expected, (new IP())->ipMatchesCidr($ip, $cidr));
    }

    public static function cidrProvider(): array
    {
        return [
            'IPv4 /0' => ['203.0.113.42', '0.0.0.0/0', true],
            'IPv4 /8' => ['10.20.30.40', '10.0.0.0/8', true],
            'IPv4 /22' => ['82.202.163.5', '82.202.160.0/22', true],
            'IPv4 /24' => ['123.123.123.123', '123.123.123.0/24', true],
            'IPv4 /32' => ['203.0.113.42', '203.0.113.42/32', true],
            'IPv4 unaligned subnet' => ['10.20.30.40', '10.1.2.3/8', true],
            'IPv4 outside /8' => ['11.0.0.1', '10.0.0.0/8', false],
            'IPv4 outside /22' => ['82.202.164.5', '82.202.160.0/22', false],
            'IPv4 outside /24' => ['123.123.124.1', '123.123.123.0/24', false],
            'IPv4 outside /32' => ['203.0.113.43', '203.0.113.42/32', false],
            'IPv6 /0' => ['2a4e:513f:9c4b:1736::1', '::/0', true],
            'IPv6 /60' => ['2a4e:513f:9c4b:173f::1', '2a4e:513f:9c4b:1730::/60', true],
            'IPv6 /64' => ['2a4e:513f:9c4b:1736:3250:b425:8ff6:7a99', '2a4e:513f:9c4b:1736::/64', true],
            'IPv6 /64 uppercase client' => ['2A4E:513F:9C4B:1736::1', '2a4e:513f:9c4b:1736::/64', true],
            'IPv6 /128' => ['2a4e:513f:9c4b:1736::1', '2a4e:513f:9c4b:1736:0:0:0:1/128', true],
            'IPv6 outside /60' => ['2a4e:513f:9c4b:1740::1', '2a4e:513f:9c4b:1730::/60', false],
            'IPv6 outside /64' => ['2a4e:513f:9c4b:1737::1', '2a4e:513f:9c4b:1736::/64', false],
            'IPv6 outside /128' => ['2a4e:513f:9c4b:1736::2', '2a4e:513f:9c4b:1736::1/128', false],
            'IPv4 client, IPv6 CIDR' => ['82.202.160.5', '2a4e:513f:9c4b:1736::/64', false],
            'IPv4 client, IPv6 /0' => ['82.202.160.5', '::/0', false],
            'IPv6 client, 0.0.0.0/8' => ['2a4e:513f:9c4b:1736::1', '0.0.0.0/8', false],
            'IPv6 client, 0.0.0.0/0' => ['2a4e:513f:9c4b:1736::1', '0.0.0.0/0', false],
            'invalid client IP' => ['not-an-ip', '0.0.0.0/8', false],
            'empty client IP' => ['', '0.0.0.0/0', false],
            'CIDR without prefix' => ['10.0.0.1', '10.0.0.0', false],
            'CIDR with empty prefix' => ['10.0.0.1', '10.0.0.0/', false],
            'CIDR with non-numeric prefix' => ['10.0.0.1', '10.0.0.0/abc', false],
            'CIDR with two prefixes' => ['10.0.0.1', '10.0.0.0/8/8', false],
            'CIDR with invalid subnet' => ['10.0.0.1', '10.0.0/8', false],
            'empty CIDR' => ['10.0.0.1', '', false],
            'IPv4 prefix above 32' => ['10.0.0.1', '10.0.0.0/33', false],
            'IPv4 negative prefix' => ['10.0.0.1', '10.0.0.0/-1', false],
            'IPv6 prefix above 128' => ['2a4e:513f:9c4b:1736::1', '2a4e:513f:9c4b:1736::/129', false],
            'IPv4 prefix with leading zero' => ['10.20.30.40', '10.0.0.0/08', true],
            'IPv4 prefix with leading space' => ['10.20.30.40', '10.0.0.0/ 8', true],
            'IPv4 prefix with trailing space' => ['10.20.30.40', '10.0.0.0/8 ', true],
            'IPv4 CIDR with surrounding spaces' => ['10.20.30.40', ' 10.0.0.0/8 ', true],
            'IPv4 prefix with plus sign' => ['10.0.0.1', '10.0.0.0/+8', false],
            'IPv4 decimal prefix' => ['10.0.0.1', '10.0.0.0/8.0', false],
            'IPv4 exponent prefix' => ['10.0.0.1', '10.0.0.0/1e1', false],
            'IPv6 client with zone id' => ['fe80::1%eth0', 'fe80::/10', false],
        ];
    }
}
