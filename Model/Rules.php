<?php

namespace Sansec\Shield\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Sansec\Shield\Model\Cache\Type\CacheType;

class Rules
{
    private Config $config;
    private CacheInterface $cache;
    private SerializerInterface $serializer;

    public function __construct(Config $config, CacheInterface $cache, SerializerInterface $serializer)
    {
        $this->config = $config;
        $this->cache = $cache;
        $this->serializer = $serializer;
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

        // download rules
        // use public key to verify signature
        // save to cache

        $rules = 'shield';
        $this->cache->save(
            $this->serializer->serialize($rules),
            CacheType::TYPE_IDENTIFIER,
            [CacheType::CACHE_TAG],
            3600
        );
    }
}
