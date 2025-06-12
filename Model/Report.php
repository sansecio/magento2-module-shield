<?php

namespace Sansec\Shield\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\Client\CurlFactory;
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

    /** @var string[] */
    private $filteredHeaders;

    public function __construct(
        Config $config,
        CurlFactory $curlFactory,
        Logger $logger,
        SerializerInterface $serializer,
        IP $ip,
        array $filteredHeaders = []
    ) {
        $this->config = $config;
        $this->curlFactory = $curlFactory;
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->ip = $ip;
        $this->filteredHeaders = $filteredHeaders;
    }

    private function getRequestHeaders(RequestInterface $request): array
    {
        $headers = $request->getHeaders()->toArray();
        foreach ($this->filteredHeaders as $filteredHeader) {
            unset($headers[$filteredHeader]);
        }
        return $headers;
    }

    private function getPackageVersion(): string
    {
        try {
            if (class_exists(\Composer\InstalledVersions::class)) {
                return \Composer\InstalledVersions::getPrettyVersion('sansec/magento2-module-shield') ?? 'unknown';
            }
        } catch (\Exception $e) {
            return 'unknown';
        }
        return 'unknown';
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
            $curl->addHeader('Expect', ''); // prevents curl from expecting 100-continue
            $curl->addHeader('Content-Type', 'application/json');
            $data = $this->serializer->serialize([
                'type' => 'report',
                'timestamp' => time(),
                'rules' => $rules,
                'version' => $this->getPackageVersion(),
                'request' => [
                    'method'  => $request->getMethod(),
                    'uri'     => $request->getRequestUri(),
                    'body'    => $request->getContent(),
                    'ips'     => $this->ip->collectRequestIPs(),
                    'headers' => $this->getRequestHeaders($request),
                    'scheme'  => $request->getScheme(),
                    'params'  => $request->getParams(),
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
