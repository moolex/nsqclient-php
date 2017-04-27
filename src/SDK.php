<?php
/**
 * SDK meta info
 * User: moyo
 * Date: 01/04/2017
 * Time: 3:21 PM
 */

namespace NSQClient;

use Psr\Log\LoggerInterface;

class SDK
{
    /**
     * sdk version
     */
    const VERSION = '1.0';

    /**
     * amazing name
     */
    const NAME = 'nsqclient';

    /**
     * @var int
     */
    public static $pubRecyclingSec = 45;

    /**
     * @var LoggerInterface
     */
    public static $presentLogger = null;

    /**
     * @var bool
     */
    public static $enabledStringPack = true;

    /**
     * @param LoggerInterface $logger
     */
    public static function setLogger(LoggerInterface $logger)
    {
        self::$presentLogger = $logger;
    }

    /**
     * @param $enable
     */
    public static function setStringPack($enable)
    {
        self::$enabledStringPack = $enable;
    }
}