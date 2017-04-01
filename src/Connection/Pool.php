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
        }
        return self::$evLoops;
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