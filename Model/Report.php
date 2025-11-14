<?php

namespace Sansec\Shield\Model;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface as Logger;

class Report
{
    /** @var IP */
    public $ip;

    /** @var Config  */
    private $config;

    /** @var CurlFactory */
    private $curlFactory;

    /** @var Logger */
    private $logger;

    /** @var SerializerInterface */
    private $serializer;

    /** @var ProductMetadataInterface */
    private $productMetadata;

    /** @var string[] */
    private $filteredHeaders;

    public function __construct(
        Config $config,
        CurlFactory $curlFactory,
        Logger $logger,
        SerializerInterface $serializer,
        IP $ip,
        ProductMetadataInterface $productMetadata,
        array $filteredHeaders = []
    ) {
        $this->config = $config;
        $this->curlFactory = $curlFactory;
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->ip = $ip;
        $this->productMetadata = $productMetadata;
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

    private function getProductVersion(): string
    {
        return sprintf(
            '%s %s %s',
            $this->productMetadata->getName(),
            $this->productMetadata->getEdition(),
            $this->productMetadata->getVersion()
        );
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
                'product_version' => $this->getProductVersion(),
                'request' => [
                    'method'  => $request->getMethod(),
                    'uri'     => $request->getRequestUri(),
                    'body'    => $request->getContent(),
                    'ips'     => $this->ip->collectRequestIPs(),
                    'headers' => $this->getRequestHeaders($request),
                    'scheme'  => $request->getScheme(),
                    'params'  => $request->getParams(),
                    'files'   => $request->getFiles(),
                ]
            ]);
            $curl->post($this->config->getReportUrl(), $data);

            if (!in_array($curl->getStatus(), [200, 429])) {
                throw new \RuntimeException(sprintf("Invalid status code: %d", $curl->getStatus()));
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf("Failed to send report: %s", $e->getMessage()));
        }
    }

    public function logBlockedRequest($rule)
    {
        $this->logger->info('Blocked request', [
            'rule' => $rule,
            'ips' => $this->ip->collectRequestIPs()
        ]);
    }
}
