<?php

namespace Sansec\Shield\Plugin;

use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\HttpFactory as HttpResponseFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\View\Element\TemplateFactory;
use Sansec\Shield\Logger\Logger;
use Sansec\Shield\Model\Config;
use Sansec\Shield\Model\Report;
use Sansec\Shield\Model\Waf;

class Shield
{
    /** @var Config */
    private $config;

    /** @var Logger */
    private $logger;

    /** @var Waf */
    private $waf;

    /** @var Report */
    private $report;

    /** @var HttpResponseFactory */
    private $responseFactory;

    /** @var TemplateFactory */
    private $templateFactory;

    public function __construct(
        Config $config,
        Logger $logger,
        Waf $waf,
        Report $report,
        HttpResponseFactory $responseFactory,
        TemplateFactory $templateFactory
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->waf = $waf;
        $this->report = $report;
        $this->responseFactory = $responseFactory;
        $this->templateFactory = $templateFactory;
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

        try {
            $matchedRules = $this->waf->matchRequest($request);
            if (empty($matchedRules)) {
                return $proceed($request);
            }

            $this->logger->info(sprintf('Matched %d rules.', count($matchedRules)));
            $this->report->sendReport($request, $matchedRules);

            foreach ($matchedRules as $rule) {
                if ($rule->action === 'block') {
                    $this->logger->info('Blocked request', ['rule' => $rule]);
                    return $this->getAccessDeniedResponse();
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
        }
        return $proceed($request);
    }
}
