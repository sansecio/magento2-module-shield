<?php

namespace Sansec\Shield\Test\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\Encryptor;
use PHPUnit\Framework\TestCase;
use Sansec\Shield\Model\Config;
use Sansec\Shield\Test\EncryptorFactory;

class ConfigTest extends TestCase
{
    private const LICENSE_KEY = 'abcdef0123456789abcdef0123456789';

    private function makeEncryptor(): Encryptor
    {
        return EncryptorFactory::create($this);
    }

    private function makeConfig($rawValue, string $path = 'sansec_shield/general/whitelisted_ips', $enabled = null): Config
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(function ($p) use ($rawValue, $path, $enabled) {
            if ($p === 'sansec_shield/general/enabled') {
                return $enabled;
            }
            return $p === $path ? $rawValue : null;
        });
        return new Config($scopeConfig, $this->makeEncryptor());
    }

    private function makeLicenseConfig($rawValue, $enabled = null): Config
    {
        return $this->makeConfig($rawValue, Config::XML_PATH_LICENSE_KEY, $enabled);
    }

    public function testLicenseKeyEmptyWhenUnset()
    {
        $this->assertSame('', $this->makeLicenseConfig(null)->getLicenseKey());
    }

    public function testPlainTextLicenseKeyIsReturnedUnchanged()
    {
        // Pre-upgrade values, and values set in env.php/config.php/env vars, stay plain text.
        $this->assertSame(self::LICENSE_KEY, $this->makeLicenseConfig(self::LICENSE_KEY)->getLicenseKey());
    }

    public function testEncryptedLicenseKeyIsDecrypted()
    {
        $encrypted = $this->makeEncryptor()->encrypt(self::LICENSE_KEY);
        $this->assertNotSame(self::LICENSE_KEY, $encrypted);
        $this->assertSame(self::LICENSE_KEY, $this->makeLicenseConfig($encrypted)->getLicenseKey());
    }

    public function testIsEnabledWithPlainTextLicenseKey()
    {
        $this->assertTrue($this->makeLicenseConfig(self::LICENSE_KEY, '1')->isEnabled());
    }

    public function testIsEnabledWithEncryptedLicenseKey()
    {
        $encrypted = $this->makeEncryptor()->encrypt(self::LICENSE_KEY);
        $this->assertTrue($this->makeLicenseConfig($encrypted, '1')->isEnabled());
    }

    public function testIsDisabledWithoutLicenseKey()
    {
        $this->assertFalse($this->makeLicenseConfig(null, '1')->isEnabled());
    }

    public function testIsDisabledWhenSwitchedOff()
    {
        $this->assertFalse($this->makeLicenseConfig(self::LICENSE_KEY, '0')->isEnabled());
    }

    public function testEncryptorOutputIsDetectedAsEncrypted()
    {
        $this->assertTrue(Config::isEncrypted($this->makeEncryptor()->encrypt(self::LICENSE_KEY)));
    }

    public function testPlainTextIsNotDetectedAsEncrypted()
    {
        $this->assertFalse(Config::isEncrypted(''));
        $this->assertFalse(Config::isEncrypted(self::LICENSE_KEY));
        $this->assertFalse(Config::isEncrypted('123:abc'));
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
