<?php
/**
 * Access endpoint info
 * User: moyo
 * Date: 31/03/2017
 * Time: 4:24 PM
 */

namespace NSQClient\Access;

use NSQClient\Async\Policy;
use NSQClient\Exception\InvalidLookupdException;

class Endpoint
{
    /**
     * @var string
     */
    private $lookupd = 'http://nsqlookupd.local.moyo.im:4161';

    /**
     * @var string
     */
    private $uniqueID = 'hash';

    /**
     * @var Asynced
     */
    private $asynced = null;

    /**
     * @var Policy
     */
    private $asyncPolicy = null;

    /**
     * Endpoint constructor.
     * @param $lookupd
     * @throws InvalidLookupdException
     */
    public function __construct($lookupd)
    {
        $this->lookupd = $lookupd;
        $this->uniqueID = spl_object_hash($this);

        // checks
        $parsed = parse_url($this->lookupd);
        if (!isset($parsed['host']))
        {
            throw new InvalidLookupdException;
        }
    }

    /**
     * @return bool
     */
    public function isAsynced()
    {
        return ! is_null($this->asynced);
    }

    /**
     * @param Asynced $asynced
     * @param Policy $policy
     * @return self
     */
    public function setAsynced(Asynced $asynced, Policy $policy = null)
    {
        $this->asynced = $asynced;
        $this->asyncPolicy = $policy ?: new Policy;
        return $this;
    }

    /**
     * @return Asynced
     */
    public function getAsynced()
    {
        return $this->asynced;
    }

    /**
     * @return Policy
     */
    public function getAsyncPolicy()
    {
        return $this->asyncPolicy;
    }

    /**
     * @return string
     */
    public function getUniqueID()
    {
        return $this->uniqueID;
    }

    /**
     * @return string
     */
    public function getLookupd()
    {
        return $this->lookupd;
    }

    /**
     * @return string
     */
    public function getConnType()
    {
        return PHP_SAPI == 'cli' ? 'tcp' : 'http';
    }
}