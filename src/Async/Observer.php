<?php
/**
 * Observer initial
 * User: moyo
 * Date: 8/29/16
 * Time: 3:36 PM
 */

namespace NSQClient\Async;

class Observer
{
    /*
     * exception watcher
     */
    const EXCEPTION_WATCHER = 'exception-watcher';

    /**
     * subscribe node state watcher (connected/closed)
     */
    const SUB_STATE_WATCHER = 'sub-state-watcher';

    /**
     * @param callable[] $eventsCallback
     * @param $eventName
     * @param $data
     */
    public static function trigger(array $eventsCallback, $eventName, $data)
    {
        if (isset($eventsCallback[$eventName]))
        {
            $watcher = $eventsCallback[$eventName];

            if (is_callable($watcher))
            {
                call_user_func_array($watcher, [$data]);
            }
        }
    }
}