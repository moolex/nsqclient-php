<?php
/**
 * Network stream
 * User: moyo
 * Date: 01/04/2017
 * Time: 3:12 PM
 */

namespace NSQClient\Contract\Network;

interface Stream
{
    /**
     * @param $buf
     */
    public function write($buf);

    /**
     * @param $len
     * @return string
     */
    public function read($len);
}