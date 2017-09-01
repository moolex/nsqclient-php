<?php
/**
 * NSQ Protocol Buffer
 * User: moyo
 * Date: 4/9/16
 * Time: 1:17 AM
 */

namespace NSQClient\Async\Foundation;

use NSQClient\Contract\Network\Stream;

class Buffer implements Stream
{
    /**
     * @var string
     */
    private $stack = null;

    /**
     * @var int
     */
    private $length = 0;

    /**
     * @var int
     */
    private $pointer = 0;

    /**
     * Buffer constructor.
     * @param $data
     */
    public function __construct($data)
    {
        $this->stack = $data;
        $this->length = strlen($data);
    }

    /**
     * @param $length
     * @return mixed
     */
    public function read($length)
    {
        $got = substr($this->stack, $this->pointer, $length);
        $this->pointer += $length;
        return $got;
    }

    /**
     * @param $buffer
     */
    public function write($buffer)
    {
        $this->stack .= $buffer;
        $this->length += strlen($buffer);
    }

    /**
     * @return bool
     */
    public function readable()
    {
        return ! $this->eof();
    }

    /**
     * @return bool
     */
    public function eof()
    {
        return $this->pointer >= $this->length;
    }
}