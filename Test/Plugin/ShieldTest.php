<?php

namespace Magento\Framework\App\Response {
    if (!class_exists(HttpFactory::class, false)) {
        class HttpFactory
        {
            public function create()
            {
            }
        }
    }
}

namespace Magento\Framework\View\Element {
    if (!class_exists(TemplateFactory::class, false)) {
        class TemplateFactory
        {
            public function create()
            {
            }
        }
    }
}

namespace Sansec\Shield\Test\Plugin {

    use Magento\Framework\App\FrontControllerInterface;
    use Magento\Framework\App\Response\Http as HttpResponse;
    use Magento\Framework\App\Response\HttpFactory as HttpResponseFactory;
    use Magento\Framework\View\Element\Template;
    use Magento\Framework\View\Element\TemplateFactory;
    use PHPUnit\Framework\MockObject\Rule\InvocationOrder;
    use PHPUnit\Framework\TestCase;
    use Psr\Log\LoggerInterface as Logger;
    use Sansec\Shield\Model\Config;
    use Sansec\Shield\Model\IP;
    use Sansec\Shield\Model\Report;
    use Sansec\Shield\Model\Rule;
    use Sansec\Shield\Model\Waf;
    use Sansec\Shield\Plugin\Shield;
    use Sansec\Shield\Test\RequestStub;

    class ShieldTest extends TestCase
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

        private function buildPlugin(
            array $whitelistedIps,
            InvocationOrder $expectedWafCalls,
            array $matchedRules = [],
            ?Report $report = null,
            string $reportTransport = Config::REPORT_TRANSPORT_DIRECT
        ): Shield {
            $config = $this->createMock(Config::class);
            $config->method('isEnabled')->willReturn(true);
            $config->method('getWhitelistedIps')->willReturn($whitelistedIps);
            $config->method('getReportTransport')->willReturn($reportTransport);

            $waf = $this->createMock(Waf::class);
            $waf->expects($expectedWafCalls)->method('matchRequest')->willReturn($matchedRules);

            return new Shield(
                $config,
                $waf,
                $report ?: $this->createMock(Report::class),
                new IP(),
                $this->buildResponseFactory(),
                $this->buildTemplateFactory()
            );
        }

        private function buildResponseFactory(): HttpResponseFactory
        {
            $factory = $this->createMock(HttpResponseFactory::class);
            $factory->method('create')->willReturn($this->createMock(HttpResponse::class));
            return $factory;
        }

        private function buildTemplateFactory(): TemplateFactory
        {
            $template = $this->createMock(Template::class);
            $template->method('setTemplate')->willReturnSelf();
            $template->method('toHtml')->willReturn('');

            $factory = $this->createMock(TemplateFactory::class);
            $factory->method('create')->willReturn($template);
            return $factory;
        }

        private function dispatch(Shield $plugin): bool
        {
            $proceedCalled = false;
            $plugin->aroundDispatch(
                $this->createMock(FrontControllerInterface::class),
                function () use (&$proceedCalled) {
                    $proceedCalled = true;
                },
                new RequestStub()
            );
            return $proceedCalled;
        }

        public function testRemoteAddrInWhitelistBypassesWaf()
        {
            $_SERVER['REMOTE_ADDR'] = '203.0.113.42';
            $plugin = $this->buildPlugin(['203.0.113.42'], $this->never());
            $this->assertTrue($this->dispatch($plugin));
        }

        public function testForwardedHeaderInWhitelistDoesNotBypassWaf()
        {
            $_SERVER['REMOTE_ADDR'] = '198.51.100.1';
            $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.42';
            $_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.42';
            $plugin = $this->buildPlugin(['203.0.113.42'], $this->once());
            $this->dispatch($plugin);
        }

        public function testEmptyWhitelistDoesNotBypassWaf()
        {
            $_SERVER['REMOTE_ADDR'] = '203.0.113.42';
            $plugin = $this->buildPlugin([], $this->once());
            $this->dispatch($plugin);
        }

        public function testMatchedRuleDefersTheReport()
        {
            $_SERVER['REMOTE_ADDR'] = '203.0.113.42';

            $rule = new Rule(new IP(), $this->createMock(Logger::class), 'report');
            $report = $this->createMock(Report::class);
            $report->expects($this->never())->method('sendReport');
            $report->expects($this->once())->method('sendReportDeferred')->with(
                $this->isInstanceOf(RequestStub::class),
                [$rule]
            );

            $plugin = $this->buildPlugin([], $this->once(), [$rule], $report);
            $this->assertTrue($this->dispatch($plugin));
        }

        public function testBlockingRuleDefersTheReportAndSkipsDispatch()
        {
            $_SERVER['REMOTE_ADDR'] = '203.0.113.42';

            $rule = new Rule(new IP(), $this->createMock(Logger::class), 'block');
            $report = $this->createMock(Report::class);
            $report->expects($this->never())->method('sendReport');
            $report->expects($this->once())->method('sendReportDeferred');
            $report->expects($this->once())->method('logBlockedRequest');

            $plugin = $this->buildPlugin([], $this->once(), [$rule], $report);
            $this->assertFalse($this->dispatch($plugin));
        }

        public function testQueueTransportPublishesTheReport()
        {
            $_SERVER['REMOTE_ADDR'] = '203.0.113.42';

            $rule = new Rule(new IP(), $this->createMock(Logger::class), 'report');
            $report = $this->createMock(Report::class);
            $report->expects($this->never())->method('sendReportDeferred');
            $report->expects($this->never())->method('sendReport');
            $report->expects($this->once())->method('publishReport')->with(
                $this->isInstanceOf(RequestStub::class),
                [$rule]
            );

            $plugin = $this->buildPlugin([], $this->once(), [$rule], $report, Config::REPORT_TRANSPORT_QUEUE);
            $this->assertTrue($this->dispatch($plugin));
        }

        public function testDirectTransportDefersTheReport()
        {
            $_SERVER['REMOTE_ADDR'] = '203.0.113.42';

            $rule = new Rule(new IP(), $this->createMock(Logger::class), 'report');
            $report = $this->createMock(Report::class);
            $report->expects($this->never())->method('publishReport');
            $report->expects($this->once())->method('sendReportDeferred');

            $plugin = $this->buildPlugin([], $this->once(), [$rule], $report, Config::REPORT_TRANSPORT_DIRECT);
            $this->assertTrue($this->dispatch($plugin));
        }
    }
}