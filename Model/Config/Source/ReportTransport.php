<?php

namespace Sansec\Shield\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Sansec\Shield\Model\Config;

class ReportTransport implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::REPORT_TRANSPORT_DIRECT, 'label' => __('Direct HTTP POST')],
            ['value' => Config::REPORT_TRANSPORT_QUEUE, 'label' => __('Message Queue')]
        ];
    }
}
