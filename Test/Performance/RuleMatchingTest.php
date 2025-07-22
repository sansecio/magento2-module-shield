<?php

namespace Sansec\Shield\Test\Performance;

use PHPUnit\Framework\TestCase;
use Sansec\Shield\Logger\Logger;
use Sansec\Shield\Model\Waf;
use Sansec\Shield\Model\Rules;
use Sansec\Shield\Model\IP;
use Sansec\Shield\Model\RuleFactory;
use Sansec\Shield\Model\ConditionFactory;
use Sansec\Shield\Test\RequestStub;

class RuleMatchingTest extends TestCase
{
    /** @var Waf */
    private $waf;

    protected function setUp(): void
    {
        parent::setUp();

        $rulesJson = file_get_contents(__DIR__ . '/../../Test/fixture/testrules.json');
        $rulesData = json_decode($rulesJson, true);

        /** @var Rules|\PHPUnit\Framework\MockObject\MockObject $rules */
        $rules = $this->createMock(Rules::class);
        $rules->method('loadRules')->willReturn($rulesData);

        // Create factories with real implementations
        $ruleFactory = new RuleFactory(new IP(), $this->createMock(Logger::class));
        $conditionFactory = new ConditionFactory();

        $this->waf = new Waf($rules, $ruleFactory, $conditionFactory);
    }

    public function testMatchRequestPerformance(): void
    {
        $request = new RequestStub(
            json_encode(['username' => 'test']),
            'POST',
            '/a/very/long/magento/url',
            [
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Connection' => 'keep-alive',
                'Content-Type' => 'application/json',
                'Sec-Ch-Ua' => '"Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
                'Sec-Ch-Ua-Mobile' => '?0',
                'Sec-Ch-Ua-Platform' => '"macOS"',
                'Sec-Fetch-Dest' => 'document',
                'Sec-Fetch-Mode' => 'navigate',
                'Sec-Fetch-Site' => 'none',
                'Sec-Fetch-User' => '?1',
                'Upgrade-Insecure-Requests' => '1',
                'X-Forwarded-For' => '127.0.0.1',
                'X-Forwarded-Proto' => 'http',
                'X-Real-Ip' => '127.0.0.1',
                'Host' => 'benchmark-store.com',
            ]
        );

        $iterations = 100000;
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Run matching multiple times
        for ($i = 0; $i < $iterations; $i++) {
            $this->waf->matchRequest($request);
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $totalTime = $endTime - $startTime;
        $averageMs = ($totalTime / $iterations) * 1000;
        $memoryIncrease = $endMemory - $startMemory;

        printf(
            "\nPerformance Test Results:\n" .
                "Average time: %.4f ms\n" .
                "Total memory increase: %.2f MB\n",
            $averageMs,
            $memoryIncrease / 1024 / 1024
        );

        $this->assertLessThan(
            10,
            $averageMs,
            "Rule matching is taking longer than expected"
        );
    }
}
