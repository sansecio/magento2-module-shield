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
    use Magento\Framework\App\Response\HttpFactory as HttpResponseFactory;
    use Magento\Framework\View\Element\TemplateFactory;
    use PHPUnit\Framework\TestCase;
    use Sansec\Shield\Model\Config;
    use Sansec\Shield\Model\IP;
    use Sansec\Shield\Model\Report;
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

        private function buildPlugin(array $whitelistedIps, $expectedWafCalls): Shield
        {
            $config = $this->createMock(Config::class);
            $config->method('isEnabled')->willReturn(true);
            $config->method('getWhitelistedIps')->willReturn($whitelistedIps);

            $waf = $this->createMock(Waf::class);
            $waf->expects($expectedWafCalls)->method('matchRequest')->willReturn([]);

            return new Shield(
                $config,
                $waf,
                $this->createMock(Report::class),
                new IP(),
                $this->createMock(HttpResponseFactory::class),
                $this->createMock(TemplateFactory::class)
            );
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
    }
}
