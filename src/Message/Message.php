<?php
/**
 * NSQ Message
 * User: moyo
 * Date: 01/04/2017
 * Time: 4:44 PM
 */

namespace NSQClient\Message;

use NSQClient\Contract\Message as MessageInterface;
use NSQClient\Contract\NMOps;

class Message implements MessageInterface
{
    /**
     * @var string
     */
    private $id = null;

    /**
     * @var string
     */
    private $payload = null;

    /**
     * @var mixed
     */
    private $data = null;

    /**
     * @var int
     */
    private $attempts = null;

    /**
     * @var int
     */
    private $timestamp = null;

    /**
     * @var int
     */
    private $deferred = null;

    /**
     * @var NMOps
     */
    private $nmOps = null;

    /**
     * Message constructor.
     * @param $payload
     * @param null $id
     * @param null $attempts
     * @param null $timestamp
     * @param NMOps $nmOps
     */
    public function __construct($payload, $id = null, $attempts = null, $timestamp = null, NMOps $nmOps = null)
    {
        $this->id = $id;
        $this->payload = $payload;
        $this->attempts = $attempts;
        $this->timestamp = $timestamp;
        $this->nmOps = $nmOps;
    }

    /**
     * @return string
     */
    public function id()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function payload()
    {
        return $this->payload;
    }

    /**
     * @return mixed
     */
    public function data()
    {
        if (is_null($this->data))
        {
            $this->data =
                $this->payload instanceof Raw
                    ? $this->payload->data()
                    : ($this->id ? json_decode($this->payload, true) : json_encode($this->payload))
            ;
        }
        return $this->data;
    }

    /**
     * @return int
     */
    public function attempts()
    {
        return $this->attempts;
    }

    /**
     * @return int
     */
    public function timestamp()
    {
        return $this->timestamp;
    }

    /**
     * just done
     */
    public function done()
    {
        $this->nmOps->finish($this->id);
    }

    /**
     * just retry
     */
    public function retry()
    {
        $this->delay(0);
    }

    /**
     * just delay
     * @param $seconds
     */
    public function delay($seconds)
    {
        $this->nmOps->requeue($this->id, $seconds * 1000);
    }

    /**
     * @param $seconds
     * @return null|int|static
     */
    public function deferred($seconds = null)
    {
        if (is_null($seconds))
        {
            return $this->deferred;
        }
        else
        {
            $this->deferred = $seconds * 1000;
            return $this;
        }
    }
}