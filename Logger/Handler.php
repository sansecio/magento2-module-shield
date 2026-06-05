<?php

namespace Sansec\Shield\Logger;

use Magento\Framework\Logger\Handler\Base;

/**
 * Log handler writing to var/log/sansec_shield.log.
 *
 * The path is set via the $fileName property rather than a di.xml `fileName`
 * constructor argument: that argument only exists from Magento 2.2, whereas
 * the property is honoured by Base in all versions (it resolves to
 * BP . $fileName when no filePath is given), so this works on 2.1 too.
 */
class Handler extends Base
{
    /**
     * @var string
     */
    protected $fileName = '/var/log/sansec_shield.log';
}
