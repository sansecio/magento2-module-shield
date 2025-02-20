<?php

namespace Sansec\Shield\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Config
{
    private const XML_PATH_ENABLED = 'sansec_shield/general/enabled';
    private const XML_PATH_LICENSE_KEY = 'sansec_shield/general/license_key';
    private const XML_PATH_RULES_URL = 'sansec_shield/general/rules_url';
    private const XML_PATH_REPORT_ENABLED = 'sansec_shield/general/report_enabled';
    private const XML_PATH_REPORT_URL = 'sansec_shield/general/report_url';

    /** @var ScopeConfigInterface */
    private $config;

    public function __construct(ScopeConfigInterface $config)
    {
        $this->config = $config;
    }

    public function isEnabled(): bool
    {
        $enabled = (bool) $this->config->getValue(self::XML_PATH_ENABLED);
        return $enabled && $this->getLicenseKey() !== '';
    }

    public function isReportEnabled(): bool
    {
        return (bool) $this->config->getValue(self::XML_PATH_REPORT_ENABLED);
    }

    public function getLicenseKey(): string
    {
        return $this->config->getValue(self::XML_PATH_LICENSE_KEY) ?? '';
    }

    public function getRulesUrl(): string
    {
        return $this->config->getValue(self::XML_PATH_RULES_URL);
    }

    public function getReportUrl(): string
    {
        return $this->config->getValue(self::XML_PATH_REPORT_URL);
    }
}
