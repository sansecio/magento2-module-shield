<?php

namespace Sansec\Shield\Test\Model;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface as Logger;
use Sansec\Shield\Model\ConditionFactory;
use Sansec\Shield\Model\IP;
use Sansec\Shield\Model\RuleFactory;
use Sansec\Shield\Model\Rules;
use Sansec\Shield\Model\Waf;
use Sansec\Shield\Test\RequestStub;

class WafTest extends TestCase
{
    /** @var Waf */
    private $waf;

    /** @var Logger */
    private $logger;

    public function setUp(): void
    {
        parent::setUp();

        $rulesPath = getenv('SANSEC_SHIELD_RULES_PATH');
        if (!file_exists($rulesPath)) {
            $this->fail("Rules file not found at path: $rulesPath");
        }
        $rulesJson = file_get_contents($rulesPath);
        if ($rulesJson === false) {
            $this->fail("Failed to read rules file at path: $rulesPath");
        }
        $rulesData = json_decode($rulesJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->fail("Invalid JSON in rules file: " . json_last_error_msg());
        }

        /** @var Rules|\PHPUnit\Framework\MockObject\MockObject $rules */
        $rules = $this->createMock(Rules::class);
        $rules->method('loadRules')->willReturn($rulesData);

        $ip = $this->createMock(IP::class);
        $ip->method('collectRequestIPs')->willReturn([
            '127.0.0.1',       // localhost
            '195.201.150.170', // sansec
            '108.162.200.42',  // cloudflare
        ]);

        $this->logger = $this->createMock(Logger::class);
        $this->waf = new Waf($rules, new RuleFactory($ip, $this->logger), new ConditionFactory());
    }

    /**
     * @dataProvider requestDataProvider
     */
    public function testRequestDoesNotMatchAnyRules($content, $method, $uri, $headers, $params, $cookies)
    {
        $request = new RequestStub($content, $method, $uri, $headers, $params, $cookies);
        $this->assertEmpty($this->waf->matchRequest($request), "Request should not match any rules");
    }

    /**
     * @dataProvider requestDataProvider
     */
    public function testRulesDoNotLogAnyWarnings($content, $method, $uri, $headers, $params, $cookies)
    {
        $this->logger->expects($this->never())->method('warning');
        $request = new RequestStub($content, $method, $uri, $headers, $params, $cookies);
        $this->waf->matchRequest($request);
    }

    public function requestDataProvider()
    {
        $data = [];
        foreach (glob(__DIR__ . '/../../Test/fixture/request/*.php') as $file) {
            $data[] = require $file;
        }
        return $data;
    }
}
