<?php

namespace Sansec\Shield\Plugin;

use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Sansec\Shield\Logger\Logger;
use Sansec\Shield\Model\Config;
use Sansec\Shield\Model\Rules;

class ShieldPlugin
{

    private Config $config;
    private Rules $rules;
    private Logger $logger;

    public function __construct(Config $config, Rules $rules, Logger $logger)
    {
        $this->config = $config;
        $this->rules = $rules;
        $this->logger = $logger;
    }

    public function beforeDispatch(FrontControllerInterface $subject, RequestInterface $request): array
    {
        if (!$this->config->isEnabled()) {
            return [$request];
        }

        $rules = $this->rules->getRules();
        // var_dump($rules);

        //var_dump($request->getContent());
        //var_dump($request->getRequestUri());
        //die();

        return [$request];
    }
}
