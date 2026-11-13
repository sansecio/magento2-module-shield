<?php

namespace Sansec\Shield\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

class Config
{
    private const XML_PATH_ENABLED = 'sansec_shield/general/enabled';
    public const XML_PATH_LICENSE_KEY = 'sansec_shield/general/license_key';
    private const XML_PATH_RULES_URL = 'sansec_shield/general/rules_url';
    private const XML_PATH_REPORT_ENABLED = 'sansec_shield/general/report_enabled';
    private const XML_PATH_REPORT_URL = 'sansec_shield/general/report_url';
    private const XML_PATH_WHITELISTED_IPS = 'sansec_shield/general/whitelisted_ips';

    /** @var ScopeConfigInterface */
    private $config;

    /** @var EncryptorInterface */
    private $encryptor;

    public function __construct(ScopeConfigInterface $config, EncryptorInterface $encryptor)
    {
        $this->config = $config;
        $this->encryptor = $encryptor;
    }

    /**
     * Whether a value is in Magento's encrypted "<keyVersion>:<cipherVersion>:<payload>" format.
     */
    public static function isEncrypted(string $value): bool
    {
        return preg_match('/^\d+:\d+:/', $value) === 1;
    }

    public function isEnabled(): bool
    {
        $enabled = (bool) $this->config->getValue(self::XML_PATH_ENABLED);
        // Check the stored value rather than the decrypted key, so a key that
        // fails to decrypt surfaces as an "Invalid license key" sync error
        // instead of silently disabling Shield.
        return $enabled && (string) $this->config->getValue(self::XML_PATH_LICENSE_KEY) !== '';
    }

    public function isReportEnabled(): bool
    {
        return (bool) $this->config->getValue(self::XML_PATH_REPORT_ENABLED);
    }

    public function getLicenseKey(): string
    {
        $value = (string) $this->config->getValue(self::XML_PATH_LICENSE_KEY);

        // Keys saved before encryption was introduced, or supplied through
        // app/etc/env.php, app/etc/config.php or environment variables, are
        // plain text and must be used as-is.
        if (!self::isEncrypted($value)) {
            return $value;
        }

        return $this->encryptor->decrypt($value);
    }

    public function getRulesUrl(): string
    {
        return $this->config->getValue(self::XML_PATH_RULES_URL);
    }

    public function getReportUrl(): string
    {
        return $this->config->getValue(self::XML_PATH_REPORT_URL);
    }

    /** @return string[] */
    public function getWhitelistedIps(): array
    {
        $raw = (string) $this->config->getValue(self::XML_PATH_WHITELISTED_IPS);
        return array_values(array_filter(array_map('trim', preg_split('/\R/', $raw))));
    }
}
