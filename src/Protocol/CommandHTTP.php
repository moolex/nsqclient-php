<?php
/**
 * NSQ Commands / HTTP
 * User: moyo
 * Date: 01/04/2017
 * Time: 5:18 PM
 */

namespace NSQClient\Protocol;

class CommandHTTP
{
    /**
     * Publish [PUB]
     * @param string $topic
     * @param string $message
     * @return array
     */
    public static function message($topic, $message, $deferred = null )
    {
        $cmd = is_null($deferred)
        ? sprintf('pub?topic=%s', $topic)
        : sprintf('pub?topic=%s&defer=%s', $topic,$deferred);
        return [
            $cmd,
            Binary::packString($message)
        ];
    }

    /**
     * Publish -multi [MPUB]
     * @param $topic
     * @param $messages
     * @return array
     */
    public static function messages($topic, $messages)
    {
        $buffer = '';
        foreach ($messages as $message)
        {
            $data = Binary::packString($message);
            $size = pack('N', strlen($data));
            $buffer .= $size . $data;
        }

        return [
            sprintf('mpub?topic=%s&binary=true', $topic),
            pack('N', count($messages)) . $buffer
        ];
    }
}