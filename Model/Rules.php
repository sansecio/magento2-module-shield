<?php

namespace Sansec\Shield\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Module\Dir;
use Magento\Framework\Serialize\SerializerInterface;
use Sansec\Shield\Model\Cache\Type\CacheType;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Sansec\Shield\Model\RuleFactory as RuleFactory;

class Rules
{
    private Config $config;
    private CacheInterface $cache;
    private SerializerInterface $serializer;
    private CurlFactory $curlFactory;
    private ModuleDirReader $moduleDirReader;
    private RuleFactory $ruleFactory;

    public function __construct(
        Config $config,
        CacheInterface $cache,
        SerializerInterface $serializer,
        CurlFactory $curlFactory,
        ModuleDirReader $moduleDirReader,
        RuleFactory $ruleFactory,
    ) {
        $this->config = $config;
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->curlFactory = $curlFactory;
        $this->moduleDirReader = $moduleDirReader;
        $this->ruleFactory = $ruleFactory;
    }

    public function loadRules(): array
    {
        $rulesData = $this->cache->load(CacheType::TYPE_IDENTIFIER);
        if (empty($rulesData)) {
            return [];
        }
        try {
            $rules = $this->serializer->unserialize($rulesData);
            $processedRules = [];
            foreach ($rules['rules'] as $ruleData) {
                $processedRules[] = $this->ruleFactory->create(['data' => $ruleData]);
            }
            return $processedRules;
        } catch (\InvalidArgumentException $exception) {
            $this->cache->remove(CacheType::TYPE_IDENTIFIER);
        }
        return [];
    }

    private function fetchRules(): array
    {
        $curl = $this->curlFactory->create();
        $curl->setCredentials($this->config->getLicenseKey(), $this->config->getLicenseKey());
        $curl->get($this->config->getRulesUrl());

        if ($curl->getStatus() !== 200) {
            throw new \RuntimeException("Invalid status code {$curl->getStatus()}");
        }

        $data = $this->serializer->unserialize($curl->getBody());
        if (!isset($data['rules']) || !isset($data['signature'])) {
            throw new \RuntimeException("Invalid response format: missing rules or signature");
        }

        return $data;
    }

    private function getPublicKey(): \OpenSSLAsymmetricKey
    {
        $etcDir = $this->moduleDirReader->getModuleDir(Dir::MODULE_ETC_DIR, 'Sansec_Shield');
        $publicKeyPath = $etcDir . DIRECTORY_SEPARATOR . 'public_key.pem';
        if (!file_exists($publicKeyPath)) {
            throw new \RuntimeException("Public key not found");
        }

        $publicKey = file_get_contents($publicKeyPath);
        if ($publicKey === false) {
            throw new \RuntimeException("Failed to read public key file: " . $publicKeyFile);
        }

        $pubkeyid = openssl_pkey_get_public($publicKey);
        if ($pubkeyid === false) {
            throw new \RuntimeException("Failed to extract public key: " . openssl_error_string());
        }
        return $pubkeyid;
    }

    private function verifySignature(string $rulesData, string $signature): bool
    {
        $result = openssl_verify($rulesData, $signature, $this->getPublicKey(), OPENSSL_ALGO_SHA256);
        if ($result === 1) {
            return true;
        } elseif ($result === 0) {
            return false;
        } else {
            throw new \RuntimeException("Signature verification error: " . openssl_error_string());
        }
    }

    public function syncRules(): array
    {
        if (!$this->config->isEnabled()) {
            return [];
        }

        $data = $this->fetchRules();

        $rulesData = base64_decode($data['rules'], true);
        if ($rulesData === false) {
            throw new \RuntimeException("Failed to decode base64 rules data");
        }

        $signature = base64_decode($data['signature'], true);
        if ($signature === false) {
            throw new \RuntimeException("Failed to decode base64 signature");
        }

        if ($this->verifySignature($rulesData, $signature)) {
            $this->cache->save(
                $rulesData,
                CacheType::TYPE_IDENTIFIER,
                [CacheType::CACHE_TAG],
                3600
            );
            return $this->serializer->unserialize($rulesData);
        }
        return [];
    }
}
