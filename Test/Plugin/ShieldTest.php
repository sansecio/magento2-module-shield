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
    use PHPUnit\Framework\MockObject\Rule\InvocationOrder;
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

        private function buildPlugin(array $whitelistedIps, InvocationOrder $expectedWafCalls): Shield
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

        /**
         * @dataProvider whitelistNotationProvider
         */
        public function testWhitelistMatchesEquivalentNotations(string $remoteAddr, string $whitelisted)
        {
            $_SERVER['REMOTE_ADDR'] = $remoteAddr;
            $plugin = $this->buildPlugin([$whitelisted], $this->never());
            $this->assertTrue($this->dispatch($plugin));
        }

        public static function whitelistNotationProvider(): array
        {
            return [
                'IPv4-mapped client, IPv4 entry' => ['::ffff:203.0.113.42', '203.0.113.42'],
                'IPv4 client, IPv4-mapped entry' => ['203.0.113.42', '::ffff:203.0.113.42'],
                'IPv6 compression variants' => ['2001:db8:0:0::1', '2001:db8::1'],
                'IPv6 case variants' => ['2001:db8::abcd', '2001:DB8::ABCD'],
                'unparsable client, identical entry' => ['localhost', 'localhost'],
            ];
        }

        /**
         * @dataProvider whitelistMismatchProvider
         */
        public function testWhitelistDoesNotMatchOtherAddresses(string $remoteAddr, string $whitelisted)
        {
            $_SERVER['REMOTE_ADDR'] = $remoteAddr;
            $plugin = $this->buildPlugin([$whitelisted], $this->once());
            $this->dispatch($plugin);
        }

        public static function whitelistMismatchProvider(): array
        {
            return [
                'different IPv4' => ['203.0.113.43', '203.0.113.42'],
                'different IPv4-mapped' => ['::ffff:203.0.113.43', '203.0.113.42'],
                'unparsable client' => ['not-an-ip', '203.0.113.42'],
                'unparsable entry' => ['203.0.113.42', '203.0.113.42x'],
            ];
        }
    }
}
