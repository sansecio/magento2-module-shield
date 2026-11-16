<?php

namespace Sansec\Shield\Setup\Patch\Data;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Sansec\Shield\Model\Config;

class EncryptLicenseKey implements DataPatchInterface
{
    /** @var ModuleDataSetupInterface */
    private $moduleDataSetup;

    /** @var EncryptorInterface */
    private $encryptor;

    public function __construct(ModuleDataSetupInterface $moduleDataSetup, EncryptorInterface $encryptor)
    {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->encryptor = $encryptor;
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }

    public function apply()
    {
        // Encrypt license keys stored in plain text by earlier versions. Values
        // that are empty or already encrypted are left untouched, and keys set
        // in app/etc/env.php, app/etc/config.php or environment variables are
        // not in this table; Config::getLicenseKey() still accepts those as-is.
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('core_config_data');

        $rows = $connection->fetchPairs(
            $connection->select()
                ->from($table, ['config_id', 'value'])
                ->where('path = ?', Config::XML_PATH_LICENSE_KEY)
        );

        foreach ($rows as $configId => $value) {
            $value = (string) $value;
            if ($value === '' || Config::isEncrypted($value)) {
                continue;
            }

            $connection->update(
                $table,
                ['value' => $this->encryptor->encrypt($value)],
                ['config_id = ?' => (int) $configId]
            );
        }

        return $this;
    }
}
