<?php
/**
 * Message raw
 * User: moyo
 * Date: 06/09/2017
 * Time: 8:35 PM
 */

namespace NSQClient\Message;

class Raw
{
    /**
     * @var string
     */
    private $data = null;

    /**
     * Raw constructor.
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * @return string
     */
    public function data()
    {
        return $this->data;
    }
}