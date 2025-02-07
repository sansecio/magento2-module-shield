<?php

namespace Sansec\Shield\Plugin;

use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Sansec\Shield\Logger\Logger;
use Sansec\Shield\Model\Config;
use Sansec\Shield\Model\Report;
use Sansec\Shield\Model\Rules;
use Sansec\Shield\Model\Waf;

class ShieldPlugin
{
    private Config $config;
    private Logger $logger;
    private Waf $waf;
    private Report $report;

    public function __construct(
        Config $config,
        Logger $logger,
        Waf $waf,
        Report $report
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->waf = $waf;
        $this->report = $report;
    }

    public function beforeDispatch(FrontControllerInterface $subject, RequestInterface $request): array
    {
        if (!$this->config->isEnabled()) {
            return [$request];
        }

        $matchedRules = $this->waf->matchRequest($request);
        if (empty($matchedRules)) {
            return [$request];
        }

        $this->logger->info(sprintf("Matched %d rules.", count($matchedRules)));
        $this->report->sendReport($request, $matchedRules);

        foreach ($matchedRules as $rule) {
            if ($rule->action === 'block') {
                $this->logger->info('Blocked request', ['rule' => $rule]);
                http_response_code(403);
                exit();
            }
        }
        return [$request];
    }
}
