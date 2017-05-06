<?php
/**
 * Graceful shutdown tools
 * User: moyo
 * Date: 06/05/2017
 * Time: 11:44 PM
 */

namespace NSQClient\Utils;

use NSQClient\Connection\Pool;
use NSQClient\Logger\Logger;
use React\EventLoop\LoopInterface;

class GracefulShutdown
{
    /**
     * 500 ms
     * @var float
     */
    private static $signalDispatchInv = 0.5;

    /**
     * @var array
     */
    private static $acceptSignals = [
        SIGHUP => 'SIGHUP',
        SIGINT => 'SIGINT',
        SIGTERM => 'SIGTERM'
    ];

    /**
     * @param LoopInterface $evLoop
     */
    public static function init(LoopInterface $evLoop)
    {
        if (extension_loaded('pcntl'))
        {
            foreach (self::$acceptSignals as $signal => $name)
            {
                pcntl_signal($signal, [__CLASS__, 'signalHandler']);
            }

            $evLoop->addPeriodicTimer(self::$signalDispatchInv, function () {
                pcntl_signal_dispatch();
            });
        }
    }

    /**
     * @param $signal
     */
    public static function signalHandler($signal)
    {
        Logger::ins()->info('Signal ['.self::$acceptSignals[$signal].'] received .. prepare shutdown');

        $instances = Pool::instances();
        foreach ($instances as $nsqdIns)
        {
            $nsqdIns->isConsumer() && $nsqdIns->closing();
        }
    }
}