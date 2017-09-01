<?php
/**
 * Timer
 * User: moyo
 * Date: 8/26/16
 * Time: 1:26 AM
 */

namespace NSQClient\Async\Foundation;

class Timer
{
    /**
     * @var array
     */
    private static $loopingJobs = [];

    /**
     * @param $uKey
     * @param $intervalMS
     * @param callable $callback
     * @return int
     */
    public static function loop($uKey, $intervalMS, callable $callback)
    {
        if (isset(self::$loopingJobs[$uKey]))
        {
            $jobID = self::$loopingJobs[$uKey];
        }
        else
        {
            self::$loopingJobs[$uKey] = $jobID = swoole_timer_tick($intervalMS, $callback);
        }

        return $jobID;
    }

    /**
     * @param $uKey
     * @return bool
     */
    public static function stop($uKey)
    {
        if (isset(self::$loopingJobs[$uKey]))
        {
            return swoole_timer_clear(self::$loopingJobs[$uKey]);
        }
        else
        {
            return false;
        }
    }

    /**
     * @param $delayMS
     * @param callable $callback
     * @return int
     */
    public static function after($delayMS, callable $callback)
    {
        return swoole_timer_after($delayMS, $callback);
    }

    /**
     * @param $tickID
     * @return bool
     */
    public static function revoke($tickID)
    {
        return swoole_timer_clear($tickID);
    }
}