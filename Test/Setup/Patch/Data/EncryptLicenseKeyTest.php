<?php

namespace Sansec\Shield\Test\Setup\Patch\Data;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use PHPUnit\Framework\TestCase;
use Sansec\Shield\Model\Config;
use Sansec\Shield\Setup\Patch\Data\EncryptLicenseKey;
use Sansec\Shield\Test\EncryptorFactory;

class EncryptLicenseKeyTest extends TestCase
{
    private const LICENSE_KEY = 'abcdef0123456789abcdef0123456789';

    /** @var Encryptor */
    private $encryptor;

    /** @var array<int, string|null> core_config_data rows for the license key path, by config_id */
    private $rows;

    /** @var int */
    private $updates;

    public function setUp(): void
    {
        parent::setUp();

        $this->encryptor = EncryptorFactory::create($this);
        $this->updates = 0;
    }

    private function makePatch(): EncryptLicenseKey
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->with('core_config_data', ['config_id', 'value'])->willReturnSelf();
        $select->method('where')->with('path = ?', Config::XML_PATH_LICENSE_KEY)->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchPairs')->willReturnCallback(function () {
            return $this->rows;
        });
        $connection->method('update')->willReturnCallback(function ($table, array $bind, array $where) {
            $this->assertSame('core_config_data', $table);
            $this->rows[$where['config_id = ?']] = $bind['value'];
            $this->updates++;
            return 1;
        });

        $setup = $this->createMock(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnArgument(0);

        return new EncryptLicenseKey($setup, $this->encryptor);
    }

    public function testEncryptsPlainTextKeyWithoutLosingIt()
    {
        $this->rows = [7 => self::LICENSE_KEY];

        $this->makePatch()->apply();

        $this->assertSame(1, $this->updates);
        $this->assertTrue(Config::isEncrypted($this->rows[7]));
        $this->assertSame(self::LICENSE_KEY, $this->encryptor->decrypt($this->rows[7]));
    }

    public function testEncryptsKeyInEveryScope()
    {
        $this->rows = [1 => self::LICENSE_KEY, 2 => 'other-scope-key'];

        $this->makePatch()->apply();

        $this->assertSame(self::LICENSE_KEY, $this->encryptor->decrypt($this->rows[1]));
        $this->assertSame('other-scope-key', $this->encryptor->decrypt($this->rows[2]));
    }

    public function testLeavesAlreadyEncryptedKeyUntouched()
    {
        $encrypted = $this->encryptor->encrypt(self::LICENSE_KEY);
        $this->rows = [7 => $encrypted];

        $this->makePatch()->apply();

        $this->assertSame(0, $this->updates);
        $this->assertSame($encrypted, $this->rows[7]);
    }

    public function testSkipsEmptyValues()
    {
        $this->rows = [7 => '', 8 => null];

        $this->makePatch()->apply();

        $this->assertSame(0, $this->updates);
        $this->assertSame([7 => '', 8 => null], $this->rows);
    }

    public function testDoesNothingWhenNoKeyIsStored()
    {
        $this->rows = [];

        $this->makePatch()->apply();

        $this->assertSame(0, $this->updates);
    }

    public function testRunningTwiceDoesNotDoubleEncrypt()
    {
        $this->rows = [7 => self::LICENSE_KEY];

        $this->makePatch()->apply();
        $this->makePatch()->apply();

        $this->assertSame(1, $this->updates);
        $this->assertSame(self::LICENSE_KEY, $this->encryptor->decrypt($this->rows[7]));
    }
}
