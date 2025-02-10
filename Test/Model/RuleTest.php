<?php

namespace Sansec\Shield\Test\Model;

use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Sansec\Shield\Model\Condition;
use Sansec\Shield\Model\IP;
use Sansec\Shield\Model\Rule;

class RuleTest extends \PHPUnit\Framework\TestCase
{
    public function testRuleContains()
    {
        $request = $this->createConfiguredMock(Http::class, ['getContent' => 'hack1337']);
        $rule = new Rule(new IP(), 'block', [
            new Condition('req.body', 'contains', '1337')
        ]);
        $this->assertTrue($rule->matches($request));
    }

    public function testRuleRegex()
    {
        $request = $this->createConfiguredMock(Http::class, ['getContent' => 'hack1337']);
        $rule = new Rule(new IP(), 'block', [
            new Condition('req.body', 'regex', 'hack\d+')
        ]);
        $this->assertTrue($rule->matches($request));
    }

    public function testRuleNetwork()
    {
        $_SERVER['REMOTE_ADDR'] = '123.123.123.123';
        $rule = new Rule(new IP(), 'block', [
            new Condition('req.ip', 'network', '123.123.123.0/24')
        ]);
        $this->assertTrue($rule->matches($this->createMock(Http::class)));
    }

    public function testRulePreprocessEquals()
    {
        $request = $this->createConfiguredMock(Http::class, ['getContent' => 'HACK1337']);
        $rule = new Rule(new IP(), 'block', [
            new Condition('req.body', 'equals', 'hack1337', ['strtolower'])
        ]);
        $this->assertTrue($rule->matches($request));
    }

    public function testMultipleRules()
    {
        $request = $this->createConfiguredMock(
            Http::class,
            [
                'getMethod' => 'POST',
                'getRequestUri' => '/rest/V1/guest-carts/123456/estimate-shipping-methods',
                'getContent' => '{"sourceData":"hack"}'
            ]
        );

        $rule = new Rule(new IP(), 'block', [
            new Condition('req.method', 'equals', 'POST'),
            new Condition('req.uri', 'contains', 'estimate-shipping-methods'),
            new Condition('req.body', 'contains', 'sourceData'),
        ]);

        $this->assertTrue($rule->matches($request));
    }
}
