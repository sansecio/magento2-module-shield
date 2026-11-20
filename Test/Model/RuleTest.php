<?php

namespace Sansec\Shield\Test\Model;

use Magento\Framework\App\Request\Http;
use Psr\Log\LoggerInterface as Logger;
use Sansec\Shield\Model\Condition;
use Sansec\Shield\Model\IP;
use Sansec\Shield\Model\Rule;
use Sansec\Shield\Test\RequestStub;

class RuleTest extends \PHPUnit\Framework\TestCase
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

    public function testRuleContainsMatchesArrayParam()
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects($this->never())->method('warning');
        $request = $this->createConfiguredMock(Http::class, [
            'getParam' => ['safe', 'hack1337']
        ]);
        $rule = new Rule(new IP(), $logger, 'block', [
            new Condition('req.param.X', 'contains', '1337')
        ]);

        $this->assertTrue($rule->matches($request));
    }

    public function testRuleRegexMatchesNestedArrayParam()
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects($this->never())->method('warning');
        $request = $this->createConfiguredMock(Http::class, [
            'getParam' => ['nested' => ['safe', 'hack1337']]
        ]);
        $rule = new Rule(new IP(), $logger, 'block', [
            new Condition('req.param.X', 'regex', 'hack\d+')
        ]);

        $this->assertTrue($rule->matches($request));
    }

    public function testRuleMatchesPostTargetFromRequestStub()
    {
        $request = new RequestStub('', 'POST', '/submit', [], ['payload' => 'hack1337']);
        $rule = new Rule(new IP(), $this->createMock(Logger::class), 'block', [
            new Condition('req.post', 'contains', 'hack1337')
        ]);

        $this->assertTrue($rule->matches($request));
    }

    public function testRuleMatchesQueryTargetFromRequestStub()
    {
        $request = new RequestStub(
            '',
            'POST',
            '/submit?payload=hack1337',
            [],
            ['payload' => 'hack1337']
        );
        $rule = new Rule(new IP(), $this->createMock(Logger::class), 'block', [
            new Condition('req.query', 'contains', 'hack1337')
        ]);

        $this->assertTrue($rule->matches($request));
    }

    public function testRulePreprocessesEachArrayParamValue()
    {
        $request = $this->createConfiguredMock(Http::class, [
            'getParam' => ['safe', 'HACK1337']
        ]);
        $rule = new Rule(new IP(), $this->createMock(Logger::class), 'block', [
            new Condition('req.param.X', 'contains', 'hack1337', ['strtolower'])
        ]);

        $this->assertTrue($rule->matches($request));
    }

    public function testRuleEqualsMatchesNestedArrayParam()
    {
        $request = $this->createConfiguredMock(Http::class, [
            'getParam' => ['nested' => ['safe', 'hack1337']]
        ]);
        $rule = new Rule(new IP(), $this->createMock(Logger::class), 'block', [
            new Condition('req.param.X', 'equals', 'hack1337')
        ]);

        $this->assertTrue($rule->matches($request));
    }

    public function testRuleDoesNotMatchArrayParamWithoutMatchingValue()
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects($this->never())->method('warning');
        $request = $this->createConfiguredMock(Http::class, [
            'getParam' => ['safe', ['also-safe']]
        ]);
        $rule = new Rule(new IP(), $logger, 'block', [
            new Condition('req.param.X', 'contains', '1337')
        ]);

        $this->assertFalse($rule->matches($request));
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

    /**
     * @dataProvider ipEqualsProvider
     */
    public function testRuleEqualsComparesNormalisedIPs(string $remoteAddr, string $conditionValue, bool $expected)
    {
        $_SERVER['REMOTE_ADDR'] = $remoteAddr;
        $rule = new Rule(new IP(['REMOTE_ADDR']), $this->createMock(Logger::class), 'block', [
            new Condition('req.ip', 'equals', $conditionValue)
        ]);
        $this->assertSame($expected, $rule->matches($this->createMock(Http::class)));
    }

    public static function ipEqualsProvider(): array
    {
        return [
            'IPv4' => ['203.0.113.5', '203.0.113.5', true],
            'IPv4-mapped client, IPv4 rule' => ['::ffff:203.0.113.5', '203.0.113.5', true],
            'IPv4 client, IPv4-mapped rule' => ['203.0.113.5', '::ffff:203.0.113.5', true],
            'IPv6 compression variants' => ['2606:4700::1', '2606:4700:0:0::1', true],
            'IPv6 case variants' => ['2606:4700::abcd', '2606:4700::ABCD', true],
            'different IPv4' => ['203.0.113.6', '203.0.113.5', false],
            'different IPv6' => ['2606:4700::2', '2606:4700::1', false],
            'invalid rule value' => ['203.0.113.5', '203.0.113.5x', false],
            'IPv4 prefix of rule value' => ['203.0.113.5', '203.0.113.50', false],
        ];
    }

    /**
     * @dataProvider ipNetworkProvider
     */
    public function testRuleNetworkMatchesNormalisedIPs(string $remoteAddr, string $cidr, bool $expected)
    {
        $_SERVER['REMOTE_ADDR'] = $remoteAddr;
        $rule = new Rule(new IP(['REMOTE_ADDR']), $this->createMock(Logger::class), 'block', [
            new Condition('req.ip', 'network', $cidr)
        ]);
        $this->assertSame($expected, $rule->matches($this->createMock(Http::class)));
    }

    public static function ipNetworkProvider(): array
    {
        return [
            'IPv4-mapped client, IPv4 CIDR' => ['::ffff:198.51.100.7', '198.51.100.0/24', true],
            'IPv4-mapped client, IPv4-mapped CIDR' => ['::ffff:198.51.100.7', '::ffff:198.51.100.0/120', true],
            'IPv4-mapped client, IPv4-mapped range' => ['::ffff:198.51.100.7', '::ffff:0:0/96', true],
            'IPv4-mapped client, IPv6 /0' => ['::ffff:198.51.100.7', '::/0', false],
            'IPv6 client, IPv6 /0' => ['2606:4700::1', '::/0', true],
            'IPv4-mapped client outside IPv4-mapped CIDR' => ['::ffff:198.51.101.7', '::ffff:198.51.100.0/120', false],
            'IPv6 client, IPv4-mapped range' => ['2606:4700::1', '::ffff:0:0/96', false],
        ];
    }
}
