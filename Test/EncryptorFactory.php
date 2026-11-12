<?php

namespace Sansec\Shield\Test;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Encryption\KeyValidator;
use Magento\Framework\Math\Random;
use PHPUnit\Framework\TestCase;

/**
 * Builds a real Magento encryptor with a fixed crypt key, without an ObjectManager.
 */
class EncryptorFactory
{
    public static function create(TestCase $test): Encryptor
    {
        $deploymentConfig = $test->getMockBuilder(DeploymentConfig::class)
            ->disableOriginalConstructor()
            ->getMock();
        $deploymentConfig->method('get')->willReturnCallback(function ($key) {
            return $key === 'crypt/key' ? '0123456789abcdef0123456789abcdef' : null;
        });
        return new Encryptor(new Random(), $deploymentConfig, new KeyValidator());
    }
}
