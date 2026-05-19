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
}
