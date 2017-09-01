<?php
/**
 * DNS manager
 * User: moyo
 * Date: 8/26/16
 * Time: 2:28 AM
 */

namespace NSQClient\Async\Piping;

use NSQClient\Async\Foundation\Timeout;
use NSQClient\Logger\Logger;

class DNS
{
    /**
     * @var array
     */
    private static $caches = [];

    /**
     * @param $domain
     * @param callable $successCallback
     * @param callable $failedCallback
     * @param $timeout
     */
    public static function resolve($domain, callable $successCallback, callable $failedCallback, $timeout = 2000)
    {
        Logger::ins()->debug('dns::resolve::begin', ['domain' => $domain]);

        swoole_async_dns_lookup(
            $domain,
            Timeout::watch($timeout, function ($domain, $ip) use ($successCallback, $failedCallback) {

                $resolved = true;

                if (empty($ip))
                {
                    Logger::ins()->notice('dns::resolve::failed', ['domain' => $domain]);

                    $resolved = false;

                    $ip = self::cache($domain);
                }

                if ($ip)
                {
                    if ($resolved)
                    {
                        Logger::ins()->debug('dns::resolve::success', ['domain' => $domain]);
                    }
                    else
                    {
                        Logger::ins()->info('dns::resolve::via-cache', ['domain' => $domain]);
                    }

                    call_user_func_array($successCallback, [$domain, self::cache($domain, $ip)]);
                }
                else
                {
                    call_user_func($failedCallback);
                }

            }, function () use ($domain, $successCallback, $failedCallback) {

                Logger::ins()->notice('dns::resolve::timeout', ['domain' => $domain]);

                $ip = self::cache($domain);

                if ($ip)
                {
                    Logger::ins()->info('dns::resolve::fallback', ['domain' => $domain]);

                    call_user_func_array($successCallback, [$domain, $ip]);
                }
                else
                {
                    call_user_func($failedCallback);
                }

            })
        );
    }

    /**
     * @param $domain
     * @param $ip
     * @return string|null
     */
    private static function cache($domain, $ip = null)
    {
        if (is_null($ip))
        {
            return isset(self::$caches[$domain]) ? self::$caches[$domain] : null;
        }
        else
        {
            self::$caches[$domain] = $ip;
            return $ip;
        }
    }
}