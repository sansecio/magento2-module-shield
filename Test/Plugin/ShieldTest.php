<?php

namespace Sansec\Shield\Test\Plugin;

use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\Response\HttpFactory as HttpResponseFactory;
use Magento\Framework\View\Element\TemplateFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sansec\Shield\Model\Config;
use Sansec\Shield\Model\ConditionFactory;
use Sansec\Shield\Model\IP;
use Sansec\Shield\Model\Report;
use Sansec\Shield\Model\RuleFactory;
use Sansec\Shield\Model\Rules;
use Sansec\Shield\Model\Waf;
use Sansec\Shield\Plugin\Shield;
use Sansec\Shield\Test\RequestStub;

class ShieldTest extends TestCase
{
    private const WHITELISTED_IP = '203.0.113.42';

    private function buildWaf(IP $ip): Waf
    {
        // Two rules that each block the whitelisted IP via different match types.
        return new Waf(
            $this->createConfiguredMock(Rules::class, [
                'loadRules' => ['rules' => [
                    ['action' => 'block', 'conditions' => [
                        ['target' => 'req.ip', 'type' => 'network', 'value' => self::WHITELISTED_IP . '/32'],
                    ]],
                    ['action' => 'block', 'conditions' => [
                        ['target' => 'req.ip', 'type' => 'equals', 'value' => self::WHITELISTED_IP],
                    ]],
                ]],
            ]),
            new RuleFactory($ip, $this->createMock(LoggerInterface::class)),
            new ConditionFactory()
        );
    }

    private function buildIp(): IP
    {
        $ip = $this->createMock(IP::class);
        $ip->method('collectRequestIPs')->willReturn([self::WHITELISTED_IP]);
        $ip->method('ipMatchesCidr')->willReturn(true);
        return $ip;
    }

    public function testRulesMatchWhenIpIsNotWhitelisted()
    {
        // Proves the rules in buildWaf() actually match this request IP, so the bypass test below isn't passing vacuously.
        $waf = $this->buildWaf($this->buildIp());
        $this->assertNotEmpty($waf->matchRequest(new RequestStub()));
    }

    public function testWhitelistedIpBypassesBlockingRules()
    {
        $ip = $this->buildIp();

        $config = $this->createMock(Config::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('getWhitelistedIps')->willReturn([self::WHITELISTED_IP]);

        $plugin = new Shield(
            $config,
            $this->buildWaf($ip),
            $this->createMock(Report::class),
            $ip,
            $this->createMock(HttpResponseFactory::class),
            $this->createMock(TemplateFactory::class)
        );

        $proceedCalled = false;
        $proceed = function () use (&$proceedCalled) {
            $proceedCalled = true;
        };

        $plugin->aroundDispatch(
            $this->createMock(FrontControllerInterface::class),
            $proceed,
            new RequestStub()
        );

        $this->assertTrue($proceedCalled);
    }
}
