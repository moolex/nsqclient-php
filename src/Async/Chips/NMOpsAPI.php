<?php
/**
 * NMOps API
 * User: moyo
 * Date: 01/09/2017
 * Time: 4:21 PM
 */

namespace NSQClient\Async\Chips;

trait NMOpsAPI
{
    /**
     * @param $messageID
     */
    public function finish($messageID)
    {
        $this->cmdFIN($messageID);
    }

    /**
     * @param $messageID
     * @param $millisecond
     */
    public function requeue($messageID, $millisecond)
    {
        $this->cmdREQ($messageID, $millisecond);
    }
}