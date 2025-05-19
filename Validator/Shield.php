<?php

namespace Sansec\Shield\Validator;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\Request\ValidatorInterface;
use Magento\Framework\App\RequestInterface;
use Sansec\Shield\Controller\Result\Denied;
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

    /** @var Denied */
    private $denied;

    public function __construct(Config $config, Logger $logger, Waf $waf, Report $report, Denied $denied)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->waf = $waf;
        $this->report = $report;
        $this->denied = $denied;
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
                    throw new InvalidRequestException($this->denied);
                }
            }
        } catch (InvalidRequestException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
