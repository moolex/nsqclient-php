<?php
/**
 * Timeout manager
 * User: moyo
 * Date: 8/26/16
 * Time: 1:28 AM
 */

namespace NSQClient\Async\Foundation;

class Timeout
{
    /**
     * used for biz success callback SIG (optional)
     */
    const SIG_CALLBACK_REACHED = '_tm_w_sig_CR';

    /**
     * timeout has been reached
     */
    const SIG_TIMEOUT_REACHED = '_tm_w_sig_TR';

    /**
     * @param $ms
     * @param callable $successCallback
     * @param callable $timeoutCallback
     * @return callable
     */
    public static function watch($ms, callable $successCallback, callable $timeoutCallback)
    {
        $timeoutTick = Timer::after($ms, $timeoutCallback);

        return function () use ($timeoutTick, $successCallback) {

            if (Timer::revoke($timeoutTick))
            {
                return call_user_func_array($successCallback, func_get_args());
            }
            else
            {
                return self::SIG_TIMEOUT_REACHED;
            }

        };
    }
}