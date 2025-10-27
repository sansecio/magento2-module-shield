<?php

namespace Sansec\Shield\Test\Model;

use Magento\Framework\App\Request\Http;
use Psr\Log\LoggerInterface as Logger;
use Sansec\Shield\Model\Condition;
use Sansec\Shield\Model\IP;
use Sansec\Shield\Model\Rule;

class RuleTest extends \PHPUnit\Framework\TestCase
{
    public function testRuleContains()
    {
        $request = $this->createConfiguredMock(Http::class, ['getContent' => 'hack1337']);
        $rule = new Rule(new IP(), $this->createMock(Logger::class), 'block', [
            new Condition('req.body', 'contains', '1337')
        ]);
        $this->assertTrue($rule->matches($request));
    }

    public function testRuleRegex()
    {
        $request = $this->createConfiguredMock(Http::class, ['getContent' => 'hack1337']);
        $rule = new Rule(new IP(), $this->createMock(Logger::class),'block', [
            new Condition('req.body', 'regex', 'hack\d+')
        ]);
        $this->assertTrue($rule->matches($request));
    }

    public function testRuleNetwork()
    {
        $ipMock = $this->getMockBuilder(IP::class)
            ->onlyMethods(['collectRequestIPs'])
            ->getMock();

        $ipMock->method('collectRequestIPs')->willReturn(['123.123.123.123']);

        $rule = new Rule($ipMock, $this->createMock(Logger::class), 'block', [
            new Condition('req.ip', 'network', '123.123.123.0/24')
        ]);
        $this->assertTrue($rule->matches($this->createMock(Http::class)));
    }

    public function testRulePreprocessEquals()
    {
        $request = $this->createConfiguredMock(Http::class, ['getContent' => 'HACK1337']);
        $rule = new Rule(new IP(), $this->createMock(Logger::class), 'block', [
            new Condition('req.body', 'equals', 'hack1337', ['strtolower'])
        ]);
        $this->assertTrue($rule->matches($request));
    }

    public function testRulePreprocessDecodeUnicode()
    {
        $request = $this->createConfiguredMock(Http::class, ['getContent' => 'hello _\u0073\u006F\u0075r\u0063\u0065\u0044a\u0074\u0061']);
        $rule = new Rule(new IP(), $this->createMock(Logger::class), 'report', [
            new Condition('req.body', 'contains', '_sourceData', ['decode_unicode'])
        ]);
        $this->assertTrue($rule->matches($request));
    }

    public function testRuleWithMultipleConditions()
    {
        $request = $this->createConfiguredMock(
            Http::class,
            [
                'getMethod' => 'POST',
                'getRequestUri' => '/rest/V1/guest-carts/123456/estimate-shipping-methods',
                'getContent' => '{"sourceData":"hack"}'
            ]
        );

        $rule = new Rule(new IP(), $this->createMock(Logger::class), 'block', [
            new Condition('req.method', 'equals', 'POST'),
            new Condition('req.uri', 'contains', 'estimate-shipping-methods'),
            new Condition('req.body', 'contains', 'sourceData'),
        ]);

        $this->assertTrue($rule->matches($request));
    }

    public function testRuleWithoutConditions()
    {
        $rule = new Rule(new IP(), $this->createMock(Logger::class), 'block', []);
        $this->assertFalse($rule->matches($this->createMock(Http::class)));
    }
}
