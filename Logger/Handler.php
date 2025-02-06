<?php

namespace Sansec\Shield\Logger;

use Magento\Framework\Logger\Handler\Base;

class Handler extends Base
{
    protected $loggerType = \Monolog\Logger::INFO;

    protected $fileName = '/var/log/sansec_shield.log';
}
