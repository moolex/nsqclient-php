<?php
/**
 * Queue API
 * User: moyo
 * Date: 01/09/2017
 * Time: 12:49 PM
 */

namespace NSQClient\Async;

use NSQClient\Access\Endpoint;
use NSQClient\Async\Connection\Lookupd;
use NSQClient\Async\Connection\Pool;
use NSQClient\Async\Piping\Node;
use NSQClient\Logger\Logger;

class Queue
{
    /**
     * @param Endpoint $endpoint
     * @param string $topic
     * @param mixed $message
     * @param callable $resultCallback
     * @return null
     */
    public static function publish(Endpoint $endpoint, $topic, $message, callable $resultCallback)
    {
        Logger::ins()->debug('queue::publish::begin', ['topic' => $topic]);

        $observers = [Observer::EXCEPTION_WATCHER => $endpoint->getAsynced()->getPubExceptionWatcher()];

        Lookupd::nodes($endpoint->getLookupd(), $topic, function ($nodes) use ($endpoint, $topic, $message, $resultCallback, $observers) {

            shuffle($nodes);
            $node = reset($nodes);

            Logger::ins()->debug('queue::publish::node::picked', ['host' => $node['host']]);

            // NOTICE : MOD_W has a locker when node been used (you can use lockCallback for auto un-locker)
            Pool::hosting(Pool::MOD_W, $node, [$topic], function ($slotID) use ($endpoint, $node, $topic) {

                $instance = new Node($node['host'], $node['port'], Node::MOD_PUB, $topic, $endpoint->getAsyncPolicy(), $slotID);
                $instance->idleRecycle($endpoint->getAsyncPolicy()->get(Policy::PUBLISH_CONN_IDLING));

                Logger::ins()->info('queue::publish::pool::new', ['slot' => $slotID, 'topic' => $topic]);

                return $instance;

            })
                ->setObservers($observers)
                ->publish($message, Pool::lockCallback($resultCallback));

        },
            $endpoint->getAsyncPolicy(), $observers,
            $endpoint->getAsyncPolicy()->get(Policy::LOOKUP_REFRESH_INV)
        );

        return null;
    }

    /**
     * @param Endpoint $endpoint
     * @param string $topic
     * @param string $channel
     * @param callable $msgCallback
     * @param int $lifecycle
     * @return null
     */
    public static function subscribe(Endpoint $endpoint, $topic, $channel, callable $msgCallback, $lifecycle = 0)
    {
        Logger::ins()->debug('queue::subscribe::begin', ['topic' => $topic]);

        $observers = [
            Observer::EXCEPTION_WATCHER => $endpoint->getAsynced()->getSubExceptionWatcher(),
            Observer::SUB_STATE_WATCHER => $endpoint->getAsynced()->getSubStateWatcher(),
        ];

        Lookupd::nodes($endpoint->getLookupd(), $topic, function ($nodes) use ($endpoint, $topic, $channel, $msgCallback, $observers, $lifecycle) {

            foreach ($nodes as $node)
            {
                Logger::ins()->debug('queue::subscribe::node::picked', ['host' => $node['host']]);

                Pool::hosting(Pool::MOD_R, $node, [$topic, $channel], function ($slotID) use ($endpoint, $node, $topic, $channel, $lifecycle) {

                    $instance = new Node($node['host'], $node['port'], Node::MOD_SUB, [$topic, $channel], $endpoint->getAsyncPolicy(), $slotID);
                    $instance->closeAfter($lifecycle);

                    Logger::ins()->info('queue::subscribe::pool::new', ['slot' => $slotID, 'topic' => $topic]);

                    return $instance;

                })
                    ->setObservers($observers)
                    ->subscribe($msgCallback);
            }

        },
            $endpoint->getAsyncPolicy(), $observers
        );

        return null;
    }
}