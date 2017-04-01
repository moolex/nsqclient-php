<?php
/**
 * Specification defines
 * User: moyo
 * Date: 31/03/2017
 * Time: 4:47 PM
 */

namespace NSQClient\Protocol;

use NSQClient\Contract\Network\Stream;
use NSQClient\Exception\UnknownFrameException;

class Specification
{
    /**
     * Frame types
     */
    const FRAME_TYPE_BROKEN = -1;
    const FRAME_TYPE_RESPONSE = 0;
    const FRAME_TYPE_ERROR = 1;
    const FRAME_TYPE_MESSAGE = 2;

    /**
     * Heartbeat response content
     */
    const HEARTBEAT = '_heartbeat_';

    /**
     * OK response content
     */
    const OK = 'OK';

    /**
     * CLOSE_WAIT response content
     */
    const CLOSE_WAIT = 'CLOSE_WAIT';

    /**
     * Read frame
     * @param Stream $buffer
     * @return array
     */
    public static function readFrame(Stream $buffer)
    {
        $size = Binary::readInt($buffer);
        $frameType = Binary::readInt($buffer);

        $frame = ['type' => $frameType, 'size'  => $size];

        // switch
        switch ($frameType)
        {
            case self::FRAME_TYPE_RESPONSE:
                $frame['response'] = Binary::readString($buffer, $size - 4);
                break;
            case self::FRAME_TYPE_ERROR:
                $frame['error'] = Binary::readString($buffer, $size - 4);
                break;
            case self::FRAME_TYPE_MESSAGE:
                $frame['timestamp'] = Binary::readLong($buffer);
                $frame['attempts'] = Binary::readShort($buffer);
                $frame['id'] = Binary::readString($buffer, 16);
                $frame['payload'] = Binary::readString($buffer, $size - 30);
                break;
            default:
                throw new UnknownFrameException(Binary::readString($buffer, $size - 4));
                break;
        }

        // check frame data
        foreach ($frame as $k => $val)
        {
            if (is_null($val))
            {
                $frame['type'] = self::FRAME_TYPE_BROKEN;
                $frame['error'] = 'broken frame (maybe network error)';
                break;
            }
        }

        return $frame;
    }

    /**
     * Test if frame is a message
     * @param array $frame
     * @return bool
     */
    public static function frameIsMessage(array $frame)
    {
        return isset($frame['type'], $frame['payload']) && $frame['type'] === self::FRAME_TYPE_MESSAGE;
    }

    /**
     * Test if frame is HEARTBEAT
     * @param array $frame
     * @return bool
     */
    public static function frameIsHeartbeat(array $frame)
    {
        return self::frameIsResponse($frame, self::HEARTBEAT);
    }

    /**
     * Test if frame is OK
     * @param array $frame
     * @return bool
     */
    public static function frameIsOK(array $frame)
    {
        return self::frameIsResponse($frame, self::OK);
    }

    /**
     * Test if frame is CLOSE_WAIT
     * @param array $frame
     * @return bool
     */
    public static function frameIsCloseWait(array $frame)
    {
        return self::frameIsResponse($frame, self::CLOSE_WAIT);
    }

    /**
     * Test if frame is ERROR
     * @param array $frame
     * @return bool
     */
    public static function frameIsError(array $frame)
    {
        return isset($frame['type']) && $frame['type'] === self::FRAME_TYPE_ERROR && isset($frame['error']);
    }

    /**
     * Test if frame is BROKEN
     * @param array $frame
     * @return bool
     */
    public static function frameIsBroken(array $frame)
    {
        return isset($frame['type']) && $frame['type'] === self::FRAME_TYPE_BROKEN;
    }

    /**
     * Test if frame is a response frame (optionally with content $response)
     * @param array $frame
     * @param string
     * @return bool
     */
    private static function frameIsResponse(array $frame, $response = null)
    {
        return
            isset($frame['type'], $frame['response'])
            &&
            $frame['type'] === self::FRAME_TYPE_RESPONSE
            &&
            ($response === null || $frame['response'] === $response);
    }
}