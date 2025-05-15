<?php

namespace Xqueue\Maileon\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class Handler extends Base
{
    /**
     * File name
     * @var string
     */
    protected $fileName = '/var/log/maileon.log';

    /**
     * Logging level
     * @var int
     */
    protected $loggerType = Logger::INFO;
}
