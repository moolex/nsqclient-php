<?php
/**
 * NSQ Commands
 * User: moyo
 * Date: 31/03/2017
 * Time: 4:40 PM
 */

namespace NSQClient\Protocol;

class Command
{
    /**
     * Magic header
     */
    const MAGIC_V2 = '  V2';

    /**
     * Magic hello
     * @return string
     */
    public static function magic()
    {
        return self::MAGIC_V2;
    }

    /**
     * Identify self [IDENTIFY]
     * @param $client_id
     * @param $hostname
     * @param $user_agent
     * @return string
     */
    public static function identify($client_id, $hostname, $user_agent)
    {
        $cmd = self::command('IDENTIFY');
        $data = json_encode([
            'client_id' => (string)$client_id,
            'hostname' => (string)$hostname,
            'user_agent' => (string)$user_agent
        ]);
        $size = pack('N', strlen($data));
        return $cmd . $size . $data;
    }

    /**
     * Subscribe [SUB]
     * @param string $topic
     * @param string $channel
     * @return string
     */
    public static function subscribe($topic, $channel)
    {
        return self::command('SUB', $topic, $channel);
    }

    /**
     * Publish [PUB]
     * @param string $topic
     * @param string $message
     * @param int $deferred
     * @return string
     */
    public static function message($topic, $message, $deferred = null)
    {
        $cmd = is_null($deferred)
            ? self::command('PUB', $topic)
            : self::command('DPUB', $topic, $deferred);
        $data = Binary::packString($message);
        $size = pack('N', strlen($data));
        return $cmd . $size . $data;
    }

    /**
     * Publish -multi [MPUB]
     * @param $topic
     * @param $messages
     * @return string
     */
    public static function messages($topic, $messages)
    {
        $cmd = self::command('MPUB', $topic);
        $msgNum = pack('N', count($messages));
        $buffer = '';
        foreach ($messages as $message)
        {
            $data = Binary::packString($message);
            $size = pack('N', strlen($data));
            $buffer .= $size . $data;
        }
        $bodySize = pack('N', strlen($msgNum . $buffer));
        return $cmd . $bodySize . $msgNum . $buffer;
    }

    /**
     * Ready [RDY]
     * @param integer $count
     * @return string
     */
    public static function ready($count)
    {
        return self::command('RDY', $count);
    }

    /**
     * Finish [FIN]
     * @param string $id
     * @return string
     */
    public static function finish($id)
    {
        return self::command('FIN', $id);
    }

    /**
     * Requeue [REQ]
     * @param string $id
     * @param integer $millisecond
     * @return string
     */
    public static function requeue($id, $millisecond)
    {
        return self::command('REQ', $id, $millisecond);
    }

    /**
     * No-op [NOP]
     * @return string
     */
    public static function nop()
    {
        return self::command('NOP');
    }

    /**
     * Cleanly close [CLS]
     * @return string
     */
    public static function close()
    {
        return self::command('CLS');
    }

    /**
     * Gen command
     * @return string
     */
    private static function command()
    {
        $args = func_get_args();
        $cmd = array_shift($args);
        return sprintf('%s %s%s', $cmd, implode(' ', $args), "\n");
    }
}