<?php
/**
 * NSQ message operates
 * User: moyo
 * Date: 01/09/2017
 * Time: 3:22 PM
 */

namespace NSQClient\Contract;

interface NMOps
{
    /**
     * @param $messageID
     */
    public function finish($messageID);

    /**
     * @param $messageID
     * @param $millisecond
     */
    public function requeue($messageID, $millisecond);
}