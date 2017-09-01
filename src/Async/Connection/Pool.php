<?php
/**
 * NSQ Connection Pool
 * User: moyo
 * Date: 4/9/16
 * Time: 4:13 PM
 */

namespace NSQClient\Async\Connection;

use NSQClient\Async\Piping\Node;
use NSQClient\Exception\GenericErrorException;
use NSQClient\Logger\Logger;

class Pool
{
    /**
     * conn for read (subscribe)
     */
    const MOD_R = 0;

    /**
     * conn for write (publish)
     */
    const MOD_W = 1;

    /**
     * state unlock
     */
    const STATE_UNLOCK = 0;

    /**
     * state locked
     */
    const STATE_LOCKED = 1;

    /**
     * stack is subscribe
     */
    const FLAG_STACK_SUB = 0;

    /**
     * stack is publish
     */
    const FLAG_STACK_PUB = 1;

    /**
     * @var int
     */
    private static $slotID = 0;

    /**
     * slotID -> stackAddress
     * @var array
     */
    private static $slotMapping = [];

    /**
     * sub nodes
     * @var array
     */
    private static $stackR = [];

    /**
     * pub nodes
     * @var array
     */
    private static $stackW = [];

    /**
     * get available node
     * @param $mode
     * @param $node
     * @param $flags
     * @param callable $proNodeInstance
     * @return Node
     */
    public static function hosting($mode, array $node, array $flags, callable $proNodeInstance)
    {
        switch ($mode)
        {
            case self::MOD_R:
                $slot = self::initialize(self::FLAG_STACK_SUB, self::$stackR, $node, $flags, $proNodeInstance);
                // force locking
                self::locking($slot['id']);
                break;
            case self::MOD_W:
                $slot = self::initialize(self::FLAG_STACK_PUB, self::$stackW, $node, $flags, $proNodeInstance);
                // auto locking
                self::locking($slot['id']);
                break;
            default:
                throw new GenericErrorException('[POOL] Illegal hosting MOD');
        }
        return $slot['node'];
    }

    /**
     * release node
     * @param $slotID
     */
    public static function release($slotID)
    {
        if (isset(self::$slotMapping[$slotID]))
        {
            $slot = &self::$slotMapping[$slotID];

            switch ($slot['flag'])
            {
                case self::FLAG_STACK_SUB:
                    unset(self::$stackR[$slot['key']][$slotID]);
                    break;
                case self::FLAG_STACK_PUB:
                    unset(self::$stackW[$slot['key']][$slotID]);
                    break;
            }

            unset($slot);
            unset(self::$slotMapping[$slotID]);

            Logger::ins()->info('pool::slot::released', ['slot' => $slotID]);
        }
    }

    /**
     * close all nodes
     * @param $flag
     */
    public static function close($flag)
    {
        switch ($flag)
        {
            case self::FLAG_STACK_SUB:
                $stack = self::$stackR;
                break;
            case self::FLAG_STACK_PUB:
                $stack = self::$stackW;
                break;
            default:
                $stack = null;
        }

        if ($stack)
        {
            foreach ($stack as $key => $pool)
            {
                foreach ($pool as $id => $slot)
                {
                    if (is_array($slot) && isset($slot['node']))
                    {
                        $node = $slot['node'];
                        if ($node instanceof Node)
                        {
                            $node->close();
                        }
                    }
                }
            }
        }
    }

    /**
     * just for MOD_W
     * @param callable $previousCallback
     * @return \Closure
     */
    public static function lockCallback(callable $previousCallback)
    {
        return function ($result, $slotID) use ($previousCallback) {
            self::unlocking($slotID);
            call_user_func_array($previousCallback, [$result]);
        };
    }

    /**
     * @param $flag
     * @param $stack
     * @param $node
     * @param $flags
     * @param callable $proNodeInstance
     * @return array
     */
    private static function initialize($flag, &$stack, $node, $flags, callable $proNodeInstance)
    {
        $key = implode('-', array_merge([$node['host']], $node['port'], $flags));

        isset($stack[$key]) || $stack[$key] = [];

        $pool = &$stack[$key];

        $found = null;
        if ($pool)
        {
            foreach ($pool as $id => $slot)
            {
                if ($slot['state'] == self::STATE_UNLOCK)
                {
                    $found = &$pool[$id];
                    break;
                }
            }
        }

        if ($found)
        {
            $slot = &$found;
        }
        else
        {
            $id = self::genSlotID();
            $slot = [
                'flag' => $flag,
                'key' => $key,
                'id' => $id,
                'state' => self::STATE_UNLOCK,
                'node' => call_user_func_array($proNodeInstance, [$id])
            ];
            $pool[$id] = &$slot;
            self::$slotMapping[$id] = &$slot;
        }

        return $slot;
    }

    /**
     * @param $slotID
     */
    private static function locking($slotID)
    {
        if (isset(self::$slotMapping[$slotID]))
        {
            self::$slotMapping[$slotID]['state'] = self::STATE_LOCKED;
        }
    }

    /**
     * @param $slotID
     */
    private static function unlocking($slotID)
    {
        if (isset(self::$slotMapping[$slotID]))
        {
            self::$slotMapping[$slotID]['state'] = self::STATE_UNLOCK;
        }
    }

    /**
     * @return int
     */
    private static function genSlotID()
    {
        return ++ self::$slotID;
    }
}