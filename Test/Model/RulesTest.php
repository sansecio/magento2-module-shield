<?php

namespace Sansec\Shield\Test\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Flag;
use Magento\Framework\Flag\FlagResource;
use Magento\Framework\FlagFactory;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sansec\Shield\Model\Config;
use Sansec\Shield\Model\Rules;
use Sansec\Shield\Model\Serializer;

class RulesTest extends TestCase
{
    private const CACHE_KEY = 'sansec_shield_rules';
    private const CACHE_LIFETIME = 300;

    /** @var array */
    private $rules = [['id' => 1, 'conditions' => []]];

    /** @var FlagFactory|MockObject */
    private $flagFactory;

    /** @var FlagResource|MockObject */
    private $flagResource;

    /** @var CacheInterface|MockObject */
    private $cache;

    /** @var Flag|MockObject */
    private $flag;

    /** @var Rules */
    private $rulesModel;

    public function setUp(): void
    {
        parent::setUp();

        $this->flag = $this->createMock(Flag::class);
        $this->flagFactory = $this->createMock(FlagFactory::class);
        $this->flagFactory->method('create')->willReturn($this->flag);
        $this->flagResource = $this->createMock(FlagResource::class);
        $this->cache = $this->createMock(CacheInterface::class);

        $this->rulesModel = new Rules(
            $this->createMock(Config::class),
            $this->flagFactory,
            $this->flagResource,
            new Serializer(),
            $this->createCurlFactoryMock(),
            $this->createMock(ModuleDirReader::class),
            $this->createMock(DateTime::class),
            $this->cache
        );
    }

    public function testCacheHitDoesNotTouchFlag()
    {
        $this->cache->method('load')->with(self::CACHE_KEY)->willReturn(json_encode($this->rules));
        $this->cache->expects($this->never())->method('save');
        $this->flagResource->expects($this->never())->method('load');

        $this->assertEquals($this->rules, $this->rulesModel->loadRules());
    }

    public function testCacheMissLoadsFlagAndPopulatesCache()
    {
        $this->cache->method('load')->willReturn(false);
        $this->flagResource->expects($this->once())->method('load');
        $this->flag->method('getFlagData')->willReturn($this->rules);
        $this->cache->expects($this->once())
            ->method('save')
            ->with(json_encode($this->rules), self::CACHE_KEY, ['SANSEC_SHIELD'], self::CACHE_LIFETIME);

        $this->assertEquals($this->rules, $this->rulesModel->loadRules());
    }

    /**
     * @dataProvider unusableCacheEntryProvider
     */
    public function testUnusableCacheEntryFallsBackToFlag(string $rulesData)
    {
        $this->cache->method('load')->willReturn($rulesData);
        $this->flagResource->expects($this->once())->method('load');
        $this->flag->method('getFlagData')->willReturn($this->rules);
        $this->cache->expects($this->once())
            ->method('save')
            ->with(json_encode($this->rules), self::CACHE_KEY, ['SANSEC_SHIELD'], self::CACHE_LIFETIME);

        $this->assertEquals($this->rules, $this->rulesModel->loadRules());
    }

    public static function unusableCacheEntryProvider(): array
    {
        return [
            'invalid JSON' => ['{invalid json'],
            'empty string' => [''],
            'JSON null' => ['null'],
            'JSON boolean' => ['false'],
            'empty array' => ['[]'],
        ];
    }

    public function testFailingCacheReadStillReturnsFlagRules()
    {
        $this->cache->method('load')->willThrowException(new \RuntimeException('backend down'));
        $this->flagResource->expects($this->once())->method('load');
        $this->flag->method('getFlagData')->willReturn($this->rules);
        $this->cache->expects($this->once())
            ->method('save')
            ->with(json_encode($this->rules), self::CACHE_KEY, ['SANSEC_SHIELD'], self::CACHE_LIFETIME);

        $this->assertEquals($this->rules, $this->rulesModel->loadRules());
    }

    public function testFailingCacheWriteStillReturnsFlagRules()
    {
        $this->cache->method('load')->willReturn(false);
        $this->flag->method('getFlagData')->willReturn($this->rules);
        $this->cache->method('save')->willThrowException(new \RuntimeException('backend down'));

        $this->assertEquals($this->rules, $this->rulesModel->loadRules());
    }

    public function testMissingFlagIsNotCached()
    {
        $this->cache->method('load')->willReturn(false);
        $this->flag->method('getFlagData')->willReturn(null);
        $this->cache->expects($this->never())->method('save');

        $this->assertEquals([], $this->rulesModel->loadRules());
    }

    public function testSaveFlagWritesCache()
    {
        $this->flagResource->expects($this->once())->method('save')->with($this->flag);
        $this->cache->expects($this->once())
            ->method('save')
            ->with(json_encode($this->rules), self::CACHE_KEY, ['SANSEC_SHIELD'], self::CACHE_LIFETIME);

        $this->invoke('saveFlag', [$this->rules]);
    }

    public function testDeleteFlagRemovesCache()
    {
        $this->flag->method('getId')->willReturn(1);
        $this->flagResource->expects($this->once())->method('delete')->with($this->flag);
        $this->cache->expects($this->once())->method('remove')->with(self::CACHE_KEY);

        $this->invoke('deleteFlag', []);
    }

    public function testFailingFlagSaveDoesNotWriteCache()
    {
        $this->flagResource->expects($this->once())
            ->method('save')
            ->with($this->flag)
            ->willThrowException(new \RuntimeException('database unavailable'));
        $this->cache->expects($this->never())->method('save');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('database unavailable');

        $this->invoke('saveFlag', [$this->rules]);
    }

    public function testDeleteMissingFlagStillRemovesCache()
    {
        $this->flag->method('getId')->willReturn(null);
        $this->flagResource->expects($this->never())->method('delete');
        $this->cache->expects($this->once())->method('remove')->with(self::CACHE_KEY);

        $this->invoke('deleteFlag', []);
    }

    public function testLegacyFlagFormatDeletesFlagAndCache()
    {
        $this->cache->method('load')->willReturn(false);
        $this->flag->method('getFlagData')->willReturn(json_encode($this->rules));
        $this->flag->method('getId')->willReturn(1);
        $this->flagResource->expects($this->once())->method('delete');
        $this->cache->expects($this->once())->method('remove')->with(self::CACHE_KEY);
        $this->cache->expects($this->never())->method('save');

        $this->assertEquals([], $this->rulesModel->loadRules());
    }

    private function createCurlFactoryMock(): CurlFactory
    {
        return $this->getMockBuilder(CurlFactory::class)
            ->disableOriginalConstructor()
            ->disableAutoload()
            ->setMethods(['create'])
            ->getMock();
    }

    private function invoke(string $method, array $arguments)
    {
        $reflection = new \ReflectionMethod(Rules::class, $method);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($this->rulesModel, $arguments);
    }
}
