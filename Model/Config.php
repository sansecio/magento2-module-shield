<?php

namespace Sansec\Shield\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Config
{
    private const XML_PATH_ENABLED = 'sansec_shield/general/enabled';
    private const XML_PATH_RULES_URL = 'sansec_shield/general/rules_url';

    private ScopeConfigInterface $config;

    public function __construct(ScopeConfigInterface $config)
    {
        $this->config = $config;
    }

    public function isEnabled(): bool
    {
        return (bool) $this->config->getValue(self::XML_PATH_ENABLED);
    }

    public function getRulesUrl(): string
    {
        return $this->config->getValue(self::XML_PATH_RULES_URL);
    }
}
