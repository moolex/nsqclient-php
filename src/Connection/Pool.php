<?php
/**
 * Connection pool
 * User: moyo
 * Date: 31/03/2017
 * Time: 4:37 PM
 */

namespace NSQClient\Connection;

use NSQClient\Contract\Network\Stream;
use NSQClient\Exception\PoolMissingSocketException;
use NSQClient\Logger\Logger;
use NSQClient\Utils\GracefulShutdown;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;

class Pool
{
    /**
     * @var Nsqd[]
     */
    private static $instances = [];

    /**
     * @var Stream[]
     */
    private static $sockMaps = [];

    /**
     * @var LoopInterface
     */
    private static $evLoops = null;

    /**
     * @var int
     */
    private static $evAttached = 0;

    /**
     * @return Nsqd[]
     */
    public static function instances()
    {
        return self::$instances;
    }

    /**
     * @param array $factors
     * @param callable $creator
     * @return Nsqd
     */
    public static function register($factors, callable $creator)
    {
        $insKey = self::getInsKey($factors);

        if (isset(self::$instances[$insKey]))
        {
            return self::$instances[$insKey];
        }

        return self::$instances[$insKey] = call_user_func($creator);
    }

    /**
     * @param resource $socket
     * @return Stream
     * @throws PoolMissingSocketException
     */
    public static function search($socket)
    {
        $expectSockID = (int)$socket;

        if (isset(self::$sockMaps[$expectSockID]))
        {
            return self::$sockMaps[$expectSockID];
        }

        foreach (self::$instances as $nsqd)
        {
            if ($nsqd->getSockID() == $expectSockID)
            {
                self::$sockMaps[$nsqd->getSockID()] = $nsqd->getSockIns();
                return $nsqd->getSockIns();
            }
        }

        throw new PoolMissingSocketException;
    }

    /**
     * @return LoopInterface
     */
    public static function getEvLoop()
    {
        if (is_null(self::$evLoops))
        {
            self::$evLoops = Factory::create();
            GracefulShutdown::init(self::$evLoops);
        }
        return self::$evLoops;
    }

    /**
     * New attach by consumer connects
     */
    public static function setEvAttached()
    {
        self::$evAttached ++;
    }

    /**
     * New detach by consumer closing
     */
    public static function setEvDetached()
    {
        self::$evAttached --;
        if (self::$evAttached <= 0)
        {
            Logger::ins()->info('ALL event detached .. perform shutdown');
            self::$evLoops && self::$evLoops->stop();
        }
    }

    /**
     * @param $factors
     * @return string
     */
    private static function getInsKey($factors)
    {
        return implode('$', $factors);
    }
}