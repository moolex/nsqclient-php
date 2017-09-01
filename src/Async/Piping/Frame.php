<?php
/**
 * NSQ Piping Frame
 * User: moyo
 * Date: 4/9/16
 * Time: 1:50 AM
 */

namespace NSQClient\Async\Piping;

use NSQClient\Exception\UnknownProtocolException;
use NSQClient\Logger\Logger;
use NSQClient\Message\Message;
use NSQClient\Protocol\Command;
use NSQClient\Protocol\Specification;

class Frame
{
    /**
     * @param $frame
     * @param Node $node
     */
    public static function processing($frame, Node $node)
    {
        if (Specification::frameIsHeartbeat($frame))
        {
            Logger::ins()->debug('frame::is::heartbeat', ['node' => (string)$node]);

            $node->pipeWriting(Command::nop());
        }
        else if (Specification::frameIsMessage($frame))
        {
            Logger::ins()->debug('frame::is::message', ['node' => (string)$node]);

            $node->msgCallback(new Message($frame['payload'], $frame['id'], $frame['attempts'], $frame['timestamp'], $node));
        }
        else if (Specification::frameIsOk($frame))
        {
            Logger::ins()->debug('frame::is::ok', ['node' => (string)$node]);

            $node->okCallback();
        }
        else if (Specification::frameIsCloseWAIT($frame))
        {
            Logger::ins()->debug('frame::is::close-wait', ['node' => (string)$node]);

            $node->pipeClosing();
        }
        else if (Specification::frameIsError($frame))
        {
            Logger::ins()->notice('frame::is::error', ['node' => (string)$node]);

            $node->errorCallback($frame['error']);
        }
        else if (Specification::frameIsBroken($frame))
        {
            // Broken frame received: %s
            Logger::ins()->error('frame::is::broken', ['node' => (string)$node]);
        }
        else
        {
            throw new UnknownProtocolException('Unexpected frame received: ' . json_encode($frame));
        }
    }
}