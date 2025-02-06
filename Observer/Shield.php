<?php

namespace Sansec\Shield\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Sansec\Shield\Logger\Logger;
use Sansec\Shield\Model\Config;
use Sansec\Shield\Model\Rules;

class Shield implements ObserverInterface
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

    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $this->logger->info("Shield is enabled");

        $rules = $this->rules->getRules();
        // var_dump($rules);

        $request = $observer->getEvent()->getRequest();
        var_dump('aaaa');
        var_dump($request->getContent());
        die('abc');
    }
}
