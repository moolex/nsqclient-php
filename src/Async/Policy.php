<?php
/**
 * Policy items
 * User: moyo
 * Date: 8/24/16
 * Time: 3:20 PM
 */

namespace NSQClient\Async;

use NSQClient\Exception\GenericErrorException;

class Policy
{
    /**
     * Timeout for dns resolver
     */
    const DNS_TIMEOUT_MS = 'dns-timeout-ms';

    /**
     * Timeout for nsqd publish
     */
    const PUB_TIMEOUT_MS = 'pub-timeout-ms';

    /**
     * Timeout for lookupd request
     */
    const LOOKUP_TIMEOUT_MS = 'lookup-timeout-ms';

    /**
     * Time(seconds) interval for trigger lookupd nodes refresh
     */
    const LOOKUP_REFRESH_INV = 'lookup-refresh-inv';

    /**
     * Time(seconds) for recycle idling connections of publish pipe
     */
    const PUBLISH_CONN_IDLING = 'pub-conn-idling';

    /**
     * @var array
     */
    private $items = [
        self::DNS_TIMEOUT_MS => 500,
        self::PUB_TIMEOUT_MS => 2000,
        self::LOOKUP_TIMEOUT_MS => 1000,
        self::LOOKUP_REFRESH_INV => 300,
        self::PUBLISH_CONN_IDLING => 900,
    ];

    /**
     * @param $key
     * @return bool
     */
    public function has($key)
    {
        return isset($this->items[$key]);
    }

    /**
     * @param $key
     * @param $val
     * @return self
     */
    public function set($key, $val)
    {
        $this->items[$key] = $val;
        return $this;
    }

    /**
     * @param $key
     * @return mixed
     */
    public function get($key)
    {
        if (isset($this->items[$key]))
        {
            return $this->items[$key];
        }
        else
        {
            throw new GenericErrorException('Policy not found');
        }
    }
}