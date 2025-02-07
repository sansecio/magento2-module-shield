<?php

namespace Sansec\Shield\Plugin;

use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Sansec\Shield\Logger\Logger;
use Sansec\Shield\Model\Config;
use Sansec\Shield\Model\Rules;
use Sansec\Shield\Model\WafFactory;

class ShieldPlugin
{
    private Config $config;
    private Rules $rules;
    private Logger $logger;
    private WafFactory $wafFactory;

    public function __construct(
        Config $config,
        Rules $rules,
        Logger $logger,
        WafFactory $wafFactory,
    ) {
        $this->config = $config;
        $this->rules = $rules;
        $this->logger = $logger;
        $this->wafFactory = $wafFactory;
    }

    public function beforeDispatch(FrontControllerInterface $subject, RequestInterface $request): array
    {
        if (!$this->config->isEnabled()) {
            return [$request];
        }

        $waf = $this->wafFactory->create(['rules' => $this->rules->loadRules()]);
        $matchedRules = $waf->matchRequest($request);
        if (empty($matchedRules)) {
            return [$request];
        }

        var_dump($matchedRules);

        return [$request];
    }
}
