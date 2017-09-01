<?php
/**
 * Lookupd connections mgr
 * User: moyo
 * Date: 8/26/16
 * Time: 2:30 AM
 */

namespace NSQClient\Async\Connection;

use NSQClient\Async\Piping\Lookup;
use NSQClient\Async\Policy;

class Lookupd
{
    /**
     * @var array
     */
    private static $daemons = [];

    /**
     * @var array
     */
    private static $waitingChains = [];

    /**
     * @param string $server
     * @param string $topic
     * @param callable $callback
     * @param Policy $policy
     * @param array $observers
     * @param int $refreshInterval
     */
    public static function nodes($server, $topic, callable $callback, Policy $policy, array $observers = [], $refreshInterval = null)
    {
        $uKey = $server.'-'.$topic;

        if (isset(self::$daemons[$uKey]))
        {
            $lookup = self::$daemons[$uKey];
        }
        else
        {
            self::$daemons[$uKey] = $lookup = new Lookup($server, $topic);
        }

        $lookup->setPolicy($policy);
        $lookup->setObservers($observers);
        $lookup->setAutoRefresh($refreshInterval);

        $lookup->nodes(self::wChainsCallback($uKey, $callback));
    }

    /**
     * @param $uKey
     * @param callable $previousCallback
     * @return callable
     */
    private static function wChainsCallback($uKey, callable $previousCallback)
    {
        // init or append chains

        if (isset(self::$waitingChains[$uKey]))
        {
            array_push(self::$waitingChains[$uKey], $previousCallback);
        }
        else
        {
            self::$waitingChains[$uKey] = [$previousCallback];
        }

        // chains cleaner

        return function () use ($uKey) {

            while (null !== $waitingCallback = array_shift(self::$waitingChains[$uKey]))
            {
                call_user_func_array($waitingCallback, func_get_args());
            }

        };
    }
}