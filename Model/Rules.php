<?php

namespace Sansec\Shield\Model;

use Magento\Framework\FlagManager;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Module\Dir;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;

class Rules
{
    private const PROTOCOL_VERSION = '1';
    private const FLAG_CODE = 'sansec_shield_rules';

    /** @var Config */
    private $config;

    /** @var FlagManager */
    private $flagManager;

    /** @var SerializerInterface */
    private $serializer;

    /** @var CurlFactory */
    private $curlFactory;

    /** @var ModuleDirReader */
    private $moduleDirReader;

    public function __construct(
        Config $config,
        FlagManager $flagManager,
        SerializerInterface $serializer,
        CurlFactory $curlFactory,
        ModuleDirReader $moduleDirReader
    ) {
        $this->config = $config;
        $this->flagManager = $flagManager;
        $this->serializer = $serializer;
        $this->curlFactory = $curlFactory;
        $this->moduleDirReader = $moduleDirReader;
    }

    public function loadRules(): array
    {
        try {
            $rulesData = $this->flagManager->getFlagData(self::FLAG_CODE);
            if (empty($rulesData)) {
                return [];
            }
            if (!is_array($rulesData)) {
                throw new \RuntimeException(); // BC: delete old flag format
            }
            return $rulesData;
        } catch (\Throwable $exception) {
            $this->flagManager->deleteFlag(self::FLAG_CODE);
        }
        return [];
    }

    private function fetchRules(): array
    {
        $curl = $this->curlFactory->create();
        $curl->setCredentials($this->config->getLicenseKey(), $this->config->getLicenseKey());
        $curl->get(sprintf("%s?v=%d", $this->config->getRulesUrl(), self::PROTOCOL_VERSION));

        if ($curl->getStatus() !== 200) {
            switch ($curl->getStatus()) {
                case 401:
                    throw new \RuntimeException("Invalid license key, please check configuration.");
                case 403:
                    $this->flagManager->deleteFlag(self::FLAG_CODE);
                    throw new \RuntimeException($curl->getBody());
                default:
                    throw new \RuntimeException("Invalid status code {$curl->getStatus()}");
            }
        }

        $data = $this->serializer->unserialize($curl->getBody());
        if (!isset($data['rules']) || !isset($data['signature'])) {
            throw new \RuntimeException("Invalid response format: missing rules or signature");
        }

        return $data;
    }

    private function getPublicKey()
    {
        $etcDir = $this->moduleDirReader->getModuleDir(Dir::MODULE_ETC_DIR, 'Sansec_Shield');
        $publicKeyPath = $etcDir . DIRECTORY_SEPARATOR . 'public_key.pem';
        if (!file_exists($publicKeyPath)) {
            throw new \RuntimeException("Public key not found");
        }

        $publicKey = file_get_contents($publicKeyPath);
        if ($publicKey === false) {
            throw new \RuntimeException("Failed to read public key file: " . $publicKeyPath);
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
        $data = $this->fetchRules();

        $rulesData = base64_decode($data['rules'], true);
        if ($rulesData === false) {
            throw new \RuntimeException("Failed to decode base64 rules data");
        }

        $signature = base64_decode($data['signature'], true);
        if ($signature === false) {
            throw new \RuntimeException("Failed to decode base64 signature");
        }

        if (!$this->verifySignature($rulesData, $signature)) {
            throw new \RuntimeException("Rule verification failed");
        }

        $rules = $this->serializer->unserialize($rulesData);
        $this->flagManager->saveFlag(self::FLAG_CODE, $rules);
        return $rules;
    }
}
