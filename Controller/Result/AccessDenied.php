<?php

namespace Sansec\Shield\Controller\Result;

use Magento\Framework\App\Response\HttpInterface as HttpResponseInterface;
use Magento\Framework\Controller\AbstractResult;
use Magento\Framework\View\Element\TemplateFactory;

class AccessDenied extends AbstractResult
{
    /** @var TemplateFactory */
    private $templateFactory;

    public function __construct(TemplateFactory $templateFactory)
    {
        $this->templateFactory = $templateFactory;
    }

    private function renderAccessDeniedTemplate(): string
    {
        return $this->templateFactory->create()
            ->setTemplate('Sansec_Shield::access_denied.phtml')
            ->toHtml();
    }

    protected function render(HttpResponseInterface $response)
    {
        $response->setStatusHeader(403);
        $response->setBody($this->renderAccessDeniedTemplate());
        return $this;
    }
}
