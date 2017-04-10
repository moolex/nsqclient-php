<?php
/**
 * Logger gate
 * User: moyo
 * Date: 10/04/2017
 * Time: 12:11 PM
 */

namespace NSQClient\Logger;

use NSQClient\SDK;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;

class Logger extends AbstractLogger
{
    /**
     * @var self
     */
    private static $instance = null;

    /**
     * @var NullLogger
     */
    private $nullLogger = null;

    /**
     * @return self
     */
    public static function ins()
    {
        if (is_null(self::$instance))
        {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     * Logger constructor.
     */
    public function __construct()
    {
        $this->nullLogger = new NullLogger;
    }

    /**
     * @param mixed $level
     * @param string $message
     * @param array $context
     */
    public function log($level, $message, array $context = array())
    {
        if (SDK::$presentLogger)
        {
            SDK::$presentLogger->log($level, $message, $context);
        }
        else
        {
            $this->nullLogger->log($level, $message, $context);
        }
    }
}