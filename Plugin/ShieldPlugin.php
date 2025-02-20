<?php

namespace Sansec\Shield\Plugin;

use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\RequestInterface;
use Sansec\Shield\Logger\Logger;
use Sansec\Shield\Model\Config;
use Sansec\Shield\Model\Report;
use Sansec\Shield\Model\Waf;

class ShieldPlugin
{
    /** @var Config */
    private $config;

    /** @var Logger */
    private $logger;

    /** @var Waf */
    private $waf;

    /** @var Report */
    private $report;

    public function __construct(Config $config, Logger $logger, Waf $waf, Report $report)
    {
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

        try {
            $matchedRules = $this->waf->matchRequest($request);
            if (empty($matchedRules)) {
                return [$request];
            }

            $this->logger->info(sprintf('Matched %d rules.', count($matchedRules)));
            $this->report->sendReport($request, $matchedRules);

            foreach ($matchedRules as $rule) {
                if ($rule->action === 'block') {
                    $this->logger->info('Blocked request', ['rule' => $rule]);
                    http_response_code(403);
                    exit();
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
        }
        return [$request];
    }
}
