<?php
/**
 * Binary code/decode
 * User: moyo
 * Date: 31/03/2017
 * Time: 4:43 PM
 */

namespace NSQClient\Protocol;

use NSQClient\Contract\Network\Stream;
use NSQClient\SDK;

class Binary
{
    /**
     * Read and unpack short (2 bytes) from buffer
     * @param Stream $buffer
     * @return int
     */
    public static function readShort(Stream $buffer)
    {
        $unpack = @unpack('n', $buffer->read(2));
        if (is_array($unpack))
        {
            list(, $res) = $unpack;
            return $res;
        }
        else
        {
            return null;
        }
    }

    /**
     * Read and unpack integer (4 bytes) from buffer
     * @param Stream $buffer
     * @return int
     */
    public static function readInt(Stream $buffer)
    {
        $unpack = @unpack('N', $buffer->read(4));
        if (is_array($unpack))
        {
            list(, $res) = $unpack;
            if (PHP_INT_SIZE !== 4)
            {
                $res = sprintf('%u', $res);
            }
            return (int)$res;
        }
        else
        {
            return null;
        }
    }

    /**
     * Read and unpack long (8 bytes) from buffer
     * @param Stream $buffer
     * @return string
     */
    public static function readLong(Stream $buffer)
    {
        $hi = @unpack('N', $buffer->read(4));
        $lo = @unpack('N', $buffer->read(4));
        if (is_array($hi) && is_array($lo))
        {
            $hi = sprintf('%u', $hi[1]);
            $lo = sprintf('%u', $lo[1]);
            return bcadd(bcmul($hi, '4294967296'), $lo);
        }
        else
        {
            return null;
        }
    }

    /**
     * Read and unpack string
     * @param Stream $buffer
     * @param int $size
     * @return string
     */
    public static function readString(Stream $buffer, $size)
    {
        if (!SDK::$enabledStringPack)
        {
            return $buffer->read($size);
        }

        $temp = @unpack('c'.$size.'chars', $buffer->read($size));
        if (is_array($temp))
        {
            $out = '';
            foreach($temp as $v)
            {
                if ($v > 0)
                {
                    $out .= chr($v);
                }
            }
            return $out;
        }
        else
        {
            return null;
        }
    }

    /**
     * Pack string
     * @param $data
     * @return string
     */
    public static function packString($data)
    {
        if (!SDK::$enabledStringPack)
        {
            return $data;
        }

        $out = '';
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++)
        {
            $out .= pack('c', ord(substr($data, $i, 1)));
        }
        return $out;
    }
}