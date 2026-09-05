<?php

namespace Sansec\Shield\Model;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface as Logger;

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

    /** @var ProductMetadataInterface */
    private $productMetadata;

    /** @var string[] */
    private $filteredHeaders;

    /** @var string[] */
    private $pendingPayloads = [];

    /** @var bool */
    private $shutdownRegistered = false;

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

    private function buildPayload(RequestInterface $request, array $rules): string
    {
        return $this->serializer->serialize([
            'type' => 'report',
            'timestamp' => time(),
            'rules' => $rules,
            'version' => $this->getPackageVersion(),
            'product_version' => $this->getProductVersion(),
            'request' => [
                'method' => $request->getMethod(),
                'uri' => $request->getRequestUri(),
                'body' => $request->getContent(),
                'ips' => $this->ip->collectRequestIPs(),
                'headers' => $this->getRequestHeaders($request),
                'scheme' => $request->getScheme(),
                'params' => $request->getParams(),
                'files' => $request->getFiles(),
            ]
        ]);
    }

    private function postPayload(string $data)
    {
        $curl = $this->curlFactory->create();
        $curl->setCredentials($this->config->getLicenseKey(), $this->config->getLicenseKey());
        $curl->setTimeout(5);
        $curl->addHeader('Expect', ''); // prevents curl from expecting 100-continue
        $curl->addHeader('Content-Type', 'application/json');
        $curl->post($this->config->getReportUrl(), $data);

        if (!in_array($curl->getStatus(), [200, 429])) {
            throw new \RuntimeException(sprintf("Invalid status code: %d", $curl->getStatus()));
        }
    }

    private function logFailure(\Exception $e)
    {
        $this->logger->error(sprintf("Failed to send report: %s", $e->getMessage()));
    }

    public function sendReport(RequestInterface $request, array $rules)
    {
        if (!$this->config->isReportEnabled()) {
            return;
        }
        try {
            $this->postPayload($this->buildPayload($request, $rules));
        } catch (\Exception $e) {
            $this->logFailure($e);
        }
    }

    public function sendReportDeferred(RequestInterface $request, array $rules)
    {
        if (!$this->config->isReportEnabled()) {
            return;
        }
        try {
            // Built now: at shutdown the request body stream, the config cache and the version cache
            // backend may already be closed.
            $this->pendingPayloads[] = $this->buildPayload($request, $rules);
        } catch (\Exception $e) {
            $this->logFailure($e);
            return;
        }
        if (!$this->shutdownRegistered) {
            $this->shutdownRegistered = true;
            register_shutdown_function([$this, 'flushDeferredReports']);
        }
    }

    public function flushDeferredReports()
    {
        $payloads = $this->pendingPayloads;
        $this->pendingPayloads = [];
        if (empty($payloads)) {
            return;
        }
        // Only PHP-FPM exposes this; on CLI and mod_php the reports are sent as before.
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        foreach ($payloads as $payload) {
            try {
                $this->postPayload($payload);
            } catch (\Exception $e) {
                $this->logFailure($e);
            }
        }
    }

    public function logBlockedRequest(RequestInterface $request, Rule $rule)
    {
        $this->logger->info('Blocked request', [
            'uri'  => $request->getRequestUri(),
            'rule' => $rule,
            'ips'  => $this->ip->collectRequestIPs()
        ]);
    }
}
