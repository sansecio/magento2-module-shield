<?php

namespace Sansec\Shield\Controller\Result;

use Magento\Framework\App\Response\HttpInterface as HttpResponseInterface;
use Magento\Framework\Controller\AbstractResult;
use Magento\Framework\View\Element\TemplateFactory;

class Denied extends AbstractResult
{
    /** @var TemplateFactory */
    private $templateFactory;

    public function __construct(TemplateFactory $templateFactory)
    {
        $this->templateFactory = $templateFactory;
    }

    private function renderDeniedTemplate(): string
    {
        return $this->templateFactory->create()
            ->setTemplate('Sansec_Shield::denied.phtml')
            ->toHtml();
    }

    protected function render(HttpResponseInterface $response)
    {
        $response->setStatusHeader(403);
        $response->setBody($this->renderDeniedTemplate());
        return $this;
    }
}
