<?php

namespace Sansec\Shield\Plugin;

use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;

class ShieldPlugin
{
    public function beforeDispatch(FrontControllerInterface $subject, RequestInterface $request): array
    {
        return [$request];
    }
}
