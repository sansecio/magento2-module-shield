<?php

namespace Sansec\Shield\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Serialize\SerializerInterface;
use Sansec\Shield\Logger\Logger;

class Report
{
    /** @var Config  */
    private $config;

    /** @var CurlFactory */
    private $curlFactory;

    /** @var Logger */
    private $logger;

    /** @var SerializerInterface */
    private $serializer;

    /** @var IP */
    private $ip;

    public function __construct(
        Config $config,
        CurlFactory $curlFactory,
        Logger $logger,
        SerializerInterface $serializer,
        IP $ip
    ) {
        $this->config = $config;
        $this->curlFactory = $curlFactory;
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->ip = $ip;
    }

    public function sendReport(RequestInterface $request, array $rules)
    {
        if (!$this->config->isReportEnabled()) {
            return;
        }
        try {
            $curl = $this->curlFactory->create();
            $curl->setCredentials($this->config->getLicenseKey(), $this->config->getLicenseKey());
            $curl->setTimeout(5);
            $curl->setHeaders(['Content-Type' => 'application/json']);
            $data = $this->serializer->serialize([
                'type' => 'report',
                'timestamp' => time(),
                'rules' => $rules,
                'request' => [
                    'method' => $request->getMethod(),
                    'path' => $request->getRequestUri(),
                    'body' => $request->getContent(),
                    'ips' => $this->ip->collectRequestIPs(),
                    'headers' => $request->getHeaders()->toArray(),
                ]
            ]);
            $curl->post($this->config->getReportUrl(), $data);

            if ($curl->getStatus() !== 200) {
                throw new \RuntimeException(sprintf("Invalid status code: %d", $curl->getStatus()));
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf("Failed to send report: %s", $e->getMessage()));
        }
    }
}
