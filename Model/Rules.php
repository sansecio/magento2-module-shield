<?php

namespace Sansec\Shield\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\SerializerInterface;
use Sansec\Shield\Model\Cache\Type\CacheType;

class Rules
{
    private Config $config;
    private CacheInterface $cache;
    private SerializerInterface $serializer;
    private CurlFactory $curlFactory;

    public function __construct(
        Config $config,
        CacheInterface $cache,
        SerializerInterface $serializer,
        CurlFactory $curlFactory
    ) {
        $this->config = $config;
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->curlFactory = $curlFactory;
    }

    public function getRules(): array
    {
        $rules = $this->cache->load(CacheType::TYPE_IDENTIFIER);
        if (empty($rules)) {
            return [];
        }
        try {
            $rules = $this->serializer->unserialize($rules);
        } catch (\InvalidArgumentException $exception) {
            return [];
        }
        return $rules;
    }

    public function syncRules(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $curl = $this->curlFactory->create();
        $curl->setCredentials($this->config->getLicenseKey(), $this->config->getLicenseKey());
        $curl->get($this->config->getRulesUrl());

        if ($curl->getStatus() !== 200) {
            throw new \RuntimeException("Invalid status code {$curl->getStatus()}");
        }
        // var_dump($curl->getBody());

        // download rules
        // use public key to verify signature
        // save to cache

        $rules = ['rules' => 'a'];
        $this->cache->save(
            $this->serializer->serialize($rules),
            CacheType::TYPE_IDENTIFIER,
            [CacheType::CACHE_TAG],
            3600
        );
    }
}
