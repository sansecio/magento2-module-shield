<?php

namespace Sansec\Shield\Validator;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\ValidatorInterface;
use Magento\Framework\App\RequestInterface;
use Sansec\Shield\Logger\Logger;
use Sansec\Shield\Model\Config;
use Sansec\Shield\Model\Report;
use Sansec\Shield\Model\Waf;

class Shield implements ValidatorInterface
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

    public function validate(RequestInterface $request, ActionInterface $action): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        try {
            $matchedRules = $this->waf->matchRequest($request);
            if (empty($matchedRules)) {
                return;
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
    }
}
