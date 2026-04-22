<?php

namespace Sansec\Shield\Test\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;
use Sansec\Shield\Model\Config;

class ConfigTest extends TestCase
{
    private function makeConfig($rawValue): Config
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(function ($path) use ($rawValue) {
            return $path === 'sansec_shield/general/whitelisted_ips' ? $rawValue : null;
        });
        return new Config($scopeConfig);
    }

    public function testReturnsEmptyArrayWhenUnset()
    {
        $this->assertSame([], $this->makeConfig(null)->getWhitelistedIps());
    }

    public function testReturnsEmptyArrayForBlankString()
    {
        $this->assertSame([], $this->makeConfig("   \n\n  \n")->getWhitelistedIps());
    }

    public function testSplitsOnNewlinesAndTrims()
    {
        $raw = "  1.2.3.4\n5.6.7.8  \n\t10.0.0.1\t";
        $this->assertSame(
            ['1.2.3.4', '5.6.7.8', '10.0.0.1'],
            $this->makeConfig($raw)->getWhitelistedIps()
        );
    }

    public function testHandlesCrlfAndCrLineEndings()
    {
        $raw = "1.1.1.1\r\n2.2.2.2\r3.3.3.3\n4.4.4.4";
        $this->assertSame(
            ['1.1.1.1', '2.2.2.2', '3.3.3.3', '4.4.4.4'],
            $this->makeConfig($raw)->getWhitelistedIps()
        );
    }

    public function testDropsBlankLinesBetweenEntries()
    {
        $raw = "1.1.1.1\n\n\n2.2.2.2\n   \n3.3.3.3\n";
        $this->assertSame(
            ['1.1.1.1', '2.2.2.2', '3.3.3.3'],
            $this->makeConfig($raw)->getWhitelistedIps()
        );
    }
}
