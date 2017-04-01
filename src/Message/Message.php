<?php
/**
 * NSQ Message
 * User: moyo
 * Date: 01/04/2017
 * Time: 4:44 PM
 */

namespace NSQClient\Message;

use NSQClient\Contract\Message as MsgInterface;

class Message implements MsgInterface
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
     * Message constructor.
     * @param $payload
     * @param null $id
     * @param null $attempts
     * @param null $timestamp
     */
    public function __construct($payload, $id = null, $attempts = null, $timestamp = null)
    {
        $this->id = $id;
        $this->payload = $payload;
        $this->attempts = $attempts;
        $this->timestamp = $timestamp;
        $this->data = $id ? json_decode($payload, true) : json_encode($payload);
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
     * @return bool
     */
    public function done()
    {

    }

    /**
     * @return bool
     */
    public function retry()
    {
        return $this->delay(0);
    }

    /**
     * @param $seconds
     * @return bool
     */
    public function delay($seconds)
    {

    }
}