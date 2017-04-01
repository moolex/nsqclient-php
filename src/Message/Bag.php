<?php
/**
 * Message bag
 * User: moyo
 * Date: 01/04/2017
 * Time: 5:07 PM
 */

namespace NSQClient\Message;

class Bag
{
    /**
     * @var Message[]
     */
    private $messages = [];

    /**
     * @param $list
     * @return self
     */
    public static function generate($list)
    {
        $bag = new self();
        foreach ($list as $item)
        {
            $bag->append(new Message($item));
        }
        return $bag;
    }

    /**
     * @param $msg
     */
    public function append($msg)
    {
        $this->messages[] = $msg;
    }

    /**
     * @return array
     */
    public function export()
    {
        $bag = [];
        foreach ($this->messages as $msg)
        {
            $bag[] = $msg->data();
        }
        return $bag;
    }
}