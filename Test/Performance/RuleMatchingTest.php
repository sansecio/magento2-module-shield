<?php

namespace Sansec\Shield\Test\Performance;

use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use PHPUnit\Framework\TestCase;
use Sansec\Shield\Model\WAF;
use Sansec\Shield\Model\Rules;
use Sansec\Shield\Model\Rule;
use Sansec\Shield\Model\Condition;
use Sansec\Shield\Model\IP;
use Sansec\Shield\Model\RuleFactory;
use Sansec\Shield\Model\ConditionFactory;

class RuleMatchingTest extends TestCase
{
    /** @var WAF */
    private $waf;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock dependencies
        $rulesJson = file_get_contents(__DIR__ . '/../../Test/fixture/testrules.json');
        $rulesData = json_decode($rulesJson, true);

        /** @var Rules|\PHPUnit\Framework\MockObject\MockObject $rules */
        $rules = $this->createMock(Rules::class);
        $rules->method('loadRules')->willReturn($rulesData);

        // Create factories with real implementations
        $ruleFactory = new RuleFactory(new IP());
        $conditionFactory = new ConditionFactory();

        $this->waf = new WAF($rules, $ruleFactory, $conditionFactory);
    }

    public function testMatchRequestPerformance(): void
    {
        // Create a dummy request
        /** @var Http|\PHPUnit\Framework\MockObject\MockObject $request */
        $request = $this->getMockBuilder(Http::class)
            ->disableOriginalConstructor()
            ->setMethods(['getMethod', 'getRequestUri', 'getContent', 'getHeaders'])
            ->getMock();

        $request->expects($this->any())
            ->method('getMethod')
            ->willReturn('POST');

        $request->expects($this->any())
            ->method('getRequestUri')
            ->willReturn('/a/very/long/magento/url');

        $request->expects($this->any())
            ->method('getContent')
            ->willReturn(json_encode(['username' => 'test']));

        $request->expects($this->any())
            ->method('getHeaders')
            ->willReturn([
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
                'Upgrade-Insecure-Requests' => '1'
            ]);

        $iterations = 10000;
        $startTime = microtime(true);

        // Run matching multiple times
        for ($i = 0; $i < $iterations; $i++) {
            $this->waf->matchRequest($request);
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        $averageMs = ($totalTime / $iterations) * 1000;

        printf(
            "\nPerformance Test Results: %.4f ms\n",
            $averageMs
        );

        // This assertion ensures the test runs but doesn't strictly test a value
        // Adjust the threshold based on your performance requirements
        $this->assertLessThan(
            10, // Maximum acceptable average time in milliseconds
            $averageMs,
            "Rule matching is taking longer than expected"
        );
    }
}
