<?php

namespace Sansec\Shield\Plugin;

use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\HttpFactory as HttpResponseFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\View\Element\TemplateFactory;
use Sansec\Shield\Model\Config;
use Sansec\Shield\Model\IP;
use Sansec\Shield\Model\Report;
use Sansec\Shield\Model\Waf;

class Shield
{
    /** @var Config */
    private $config;

    /** @var Waf */
    private $waf;

    /** @var Report */
    private $report;

    /** @var IP */
    private $ip;

    /** @var HttpResponseFactory */
    private $responseFactory;

    /** @var TemplateFactory */
    private $templateFactory;

    public function __construct(
        Config $config,
        Waf $waf,
        Report $report,
        IP $ip,
        HttpResponseFactory $responseFactory,
        TemplateFactory $templateFactory
    ) {
        $this->config = $config;
        $this->waf = $waf;
        $this->report = $report;
        $this->ip = $ip;
        $this->responseFactory = $responseFactory;
        $this->templateFactory = $templateFactory;
    }

    private function isRequestWhitelisted(): bool
    {
        $whitelisted = $this->config->getWhitelistedIps();
        if (empty($whitelisted)) {
            return false;
        }
        $remoteAddr = $this->ip->getRemoteAddr();
        if ($remoteAddr === null) {
            return false;
        }
        return in_array($remoteAddr, $whitelisted, true);
    }

    private function getAccessDeniedResponse(): ResponseInterface
    {
        $response = $this->responseFactory->create();
        $response->setHttpResponseCode(403);
        $response->setBody(
            $this->templateFactory->create()->setTemplate('Sansec_Shield::access_denied.phtml')->toHtml()
        );
        return $response;
    }

    public function aroundDispatch(FrontControllerInterface $subject, callable $proceed, RequestInterface $request)
    {
        if (!$this->config->isEnabled()) {
            return $proceed($request);
        }

        if ($this->isRequestWhitelisted()) {
            return $proceed($request);
        }

        $matchedRules = $this->waf->matchRequest($request);
        if (empty($matchedRules)) {
            return $proceed($request);
        }

        if ($this->config->getReportTransport() === Config::REPORT_TRANSPORT_QUEUE) {
            $this->report->publishReport($request, $matchedRules);
        } else {
            $this->report->sendReportDeferred($request, $matchedRules);
        }

        foreach ($matchedRules as $rule) {
            if ($rule->action === 'block') {
                $this->report->logBlockedRequest($request, $rule);
                return $this->getAccessDeniedResponse();
            }
        }

        return $proceed($request);
    }
}
