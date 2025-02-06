<?php

namespace Sansec\Shield\Model\Cache\Type;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;

class CacheType extends TagScope
{
    public const TYPE_IDENTIFIER = 'sansec_shield';

    public const CACHE_TAG = 'SANSEC_SHIELD';

    public function __construct(FrontendPool $cacheFrontendPool)
    {
        parent::__construct(
            $cacheFrontendPool->get(self::TYPE_IDENTIFIER),
            self::CACHE_TAG
        );
    }
}
