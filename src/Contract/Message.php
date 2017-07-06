<?php
/**
 * Message interface
 * User: moyo
 * Date: 01/04/2017
 * Time: 4:39 PM
 */

namespace NSQClient\Contract;

interface Message
{
    /**
     * Get message ID
     * @return int
     */
    public function id();

    /**
     * Get message payload (raw)
     * @return string
     */
    public function payload();

    /**
     * Get message data (serialized/un-serialized)
     * @return mixed
     */
    public function data();

    /**
     * Get attempts
     * @return int
     */
    public function attempts();

    /**
     * Get timestamp
     * @return int
     */
    public function timestamp();

    /**
     * Make msg is done
     */
    public function done();

    /**
     * Make retry with msg
     */
    public function retry();

    /**
     * Make delay with msg
     * @param $seconds
     */
    public function delay($seconds);

    /**
     * Set msg deferred or get msg's deferred milliseconds
     * @param $seconds
     * @return int|static
     */
    public function deferred($seconds = null);
}