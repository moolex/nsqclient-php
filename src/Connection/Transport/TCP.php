<?php
/**
 * TCP Client for NSQ
 * User: moyo
 * Date: 31/03/2017
 * Time: 6:03 PM
 */

namespace NSQClient\Connection\Transport;

use NSQClient\Contract\Network\Stream;
use NSQClient\Exception\NetworkSocketException;
use NSQClient\Exception\NetworkTimeoutException;

class TCP implements Stream
{
    /**
     * @var string
     */
    private $host = '127.0.0.1';

    /**
     * @var int
     */
    private $port = 4150;

    /**
     * @var bool
     */
    private $blocking = true;

    /**
     * @var resource
     */
    private $socket = null;

    /**
     * @var callable
     */
    private $handshake = null;

    /**
     * @var int
     */
    private $readTimeoutSec = 5;

    /**
     * @var int
     */
    private $readTimeoutUsec = 0;

    /**
     * @var int
     */
    private $writeTimeoutSec = 5;

    /**
     * @var int
     */
    private $writeTimeoutUsec = 0;

    /**
     * @var int
     */
    private $connRecyclingSec = 0;

    /**
     * @var int
     */
    private $connEstablishedTime = 0;

    /**
     * @param $host
     * @param $port
     */
    public function setTarget($host, $port)
    {
        $this->host = $host;
        $this->port = $port;
    }

    /**
     * @param $switch
     */
    public function setBlocking($switch)
    {
        $this->blocking = $switch ? true : false;
    }

    /**
     * @param string $ch
     * @param float $time
     */
    public function setTimeout($ch = 'rw', $time = 5.0)
    {
        if ($ch === 'r' || $ch === 'rw')
        {
            $this->readTimeoutSec = floor($time);
            $this->readTimeoutUsec = ($time - $this->readTimeoutSec) * 1000000;
        }

        if ($ch === 'w' || $ch === 'rw')
        {
            $this->writeTimeoutSec = floor($time);
            $this->writeTimeoutUsec = ($time - $this->writeTimeoutSec) * 1000000;
        }
    }

    /**
     * @param $seconds
     */
    public function setRecycling($seconds)
    {
        $this->connRecyclingSec = $seconds;
    }

    /**
     * @param callable $processor
     */
    public function setHandshake(callable $processor)
    {
        $this->handshake = $processor;
    }

    /**
     * @return resource
     */
    public function socket()
    {
        if ($this->socket)
        {
            if (
                $this->connRecyclingSec
                &&
                $this->connEstablishedTime
                &&
                (time() - $this->connEstablishedTime > $this->connRecyclingSec)
            )
            {
                $this->close();
            }
            else
            {
                return $this->socket;
            }
        }

        $netErrNo = $netErrMsg = null;

        $this->socket = fsockopen($this->host, $this->port, $netErrNo, $netErrMsg);

        if ($this->socket === false)
        {
            throw new NetworkSocketException("Connecting failed [{$this->host}:{$this->port}] - {$netErrMsg}", $netErrNo);
        }
        else
        {
            $this->connEstablishedTime = time();
        }

        stream_set_blocking($this->socket, $this->blocking ? 1 : 0);

        if (is_callable($this->handshake))
        {
            call_user_func($this->handshake, $this);
        }

        return $this->socket;
    }

    /**
     * @param $buf
     */
    public function write($buf)
    {
        $null = null;
        $socket = $this->socket();
        $writeCh = [$socket];

        while (strlen($buf) > 0)
        {
            $writable = stream_select($null, $writeCh, $null, $this->writeTimeoutSec, $this->writeTimeoutUsec);
            if ($writable > 0)
            {
                $wroteLen = stream_socket_sendto($socket, $buf);
                if ($wroteLen === -1 || $wroteLen === false)
                {
                    throw new NetworkSocketException("Writing failed [{$this->host}:{$this->port}](1)");
                }
                $buf = substr($buf, $wroteLen);
            }
            else if ($writable === 0)
            {
                throw new NetworkTimeoutException("Writing timeout [{$this->host}:{$this->port}]");
            }
            else
            {
                throw new NetworkSocketException("Writing failed [{$this->host}:{$this->port}](2)");
            }
        }
    }

    /**
     * @param $len
     * @return string
     */
    public function read($len)
    {
        $null = null;
        $socket = $this->socket();
        $readCh = [$socket];

        $remainingLen = $len;
        $buffer = '';

        while (strlen($buffer) < $len)
        {
            $readable = stream_select($readCh, $null, $null, $this->readTimeoutSec, $this->readTimeoutUsec);
            if ($readable > 0)
            {
                $recv = stream_socket_recvfrom($socket, $remainingLen);
                if ($recv === false)
                {
                    throw new NetworkSocketException("Reading failed [{$this->host}:{$this->port}](1)");
                }
                else if ($recv === '')
                {
                    throw new NetworkSocketException("Reading failed [{$this->host}:{$this->port}](2)");
                }
                else
                {
                    $buffer .= $recv;
                    $remainingLen -= strlen($recv);
                }
            }
            else if ($readable === 0)
            {
                throw new NetworkTimeoutException("Reading timeout [{$this->host}:{$this->port}]");
            }
            else
            {
                throw new NetworkSocketException("Reading failed [{$this->host}:{$this->port}](3)");
            }
        }

        return $buffer;
    }

    /**
     * @return bool
     */
    public function close()
    {
        $closed = fclose($this->socket);
        $this->socket = null;
        return $closed;
    }
}