<?php
/**
 * NSQ Piping Lookup
 * User: moyo
 * Date: 4/4/16
 * Time: 11:48 PM
 */

namespace NSQClient\Async\Piping;

use NSQClient\Async\Foundation\Timeout;
use NSQClient\Async\Foundation\Timer;
use NSQClient\Async\Observer;
use NSQClient\Async\Policy;
use NSQClient\Exception\InvalidLookupdException;
use NSQClient\Exception\LookupTopicException;
use NSQClient\Logger\Logger;

class Lookup
{
    /**
     * uri for custom node
     * @var string
     */
    const URI_LOOKUP = '/lookup?topic=%s';

    /**
     * action is success
     */
    const ACTION_SUCCESS = true;

    /**
     * action is failed
     */
    const ACTION_FAILED = false;

    /**
     * @var string
     */
    private $protocol = '';

    /**
     * @var string
     */
    private $host = '';

    /**
     * @var string
     */
    private $port = '';

    /**
     * @var string
     */
    private $topic = '';

    /**
     * @var Policy
     */
    private $policy = null;

    /**
     * @var int
     */
    private $refreshInterval = null;

    /**
     * @var mixed
     */
    private $originCallback = null;

    /**
     * @var mixed
     */
    private $blockingCallback = null;

    /**
     * @var array
     */
    private $observers = [];

    /**
     * @var array
     */
    private $nodesCache = [];

    /**
     * @var int
     */
    private $slotID = 0;

    /**
     * @var string
     */
    private $httpUri = '';

    /**
     * http clients pool
     * @var array
     */
    private $httpClients = [];

    /**
     * Lookup constructor.
     * @param $server
     * @param $topic
     */
    public function __construct($server, $topic)
    {
        $this->topic = $topic;

        $parts = parse_url($server);
        $this->protocol = isset($parts['scheme']) ? $parts['scheme'] : 'unknown';
        $this->host = isset($parts['host']) ? $parts['host'] : '127.0.0.1';
        $this->port = isset($parts['port']) ? $parts['port'] : 4161;

        if ($this->protocol == 'http')
        {
            $this->httpUri = sprintf(self::URI_LOOKUP, $this->topic);
        }
        else
        {
            throw new InvalidLookupdException('Lookup protocol not supported');
        }
    }

    /**
     * @param $policy
     */
    public function setPolicy(Policy $policy)
    {
        $this->policy = $policy;
    }

    /**
     * @param $observers
     */
    public function setObservers(array $observers)
    {
        $this->observers = $observers;
    }

    /**
     * @param $interval
     */
    public function setAutoRefresh($interval)
    {
        if (is_null($this->refreshInterval) && $interval)
        {
            $this->refreshInterval = $interval;

            // add refresh-job

            Timer::loop($this->uniqueKey(), $interval * 1000, [$this, 'handlingRefreshTimer']);

            $this->handlingRefreshTimer();
        }
    }

    /**
     * refresh timer
     */
    public function handlingRefreshTimer()
    {
        $uKey = $this->uniqueKey();

        $blockingCallback = &$this->blockingCallback;

        $this->fetching(function ($success, $nodes = []) use ($uKey, &$blockingCallback) {

            if ($success)
            {
                Logger::ins()->debug('lookup::refresh-job::success', ['key' => $uKey]);

                if ($blockingCallback)
                {
                    $originCallback = $blockingCallback;
                    $blockingCallback = null;

                    call_user_func_array($originCallback, [$nodes]);
                }

                // writing cache

                $this->nodesCache[$uKey] = ['refreshAT' => time(), 'nodes' => $nodes];

                Logger::ins()->debug('lookup::cache::updated', ['key' => $uKey]);
            }
            else
            {
                Logger::ins()->notice('lookup::refresh-job::failed', ['key' => $uKey]);

                if ($blockingCallback)
                {
                    $blockingCallback = null;
                    Observer::trigger($this->observers, Observer::EXCEPTION_WATCHER, new LookupTopicException('Lookup nodes failed (2)'));
                }
            }

        });
    }

    /**
     * @param callable $callback
     */
    public function nodes(callable $callback)
    {
        $this->originCallback = $callback;

        if ($this->refreshInterval)
        {
            $this->fetchViaCache();
        }
        else
        {
            $this->fetchViaNetwork();
        }
    }

    /**
     * via caching
     */
    private function fetchViaCache()
    {
        $cacheHit = $cacheExpired = false;

        $uKey = $this->uniqueKey();

        if (isset($this->nodesCache[$uKey]))
        {
            // hit cache
            $cacheHit = true;

            $cacheSlot = $this->nodesCache[$uKey];

            if (time() - $cacheSlot['refreshAT'] > $this->refreshInterval)
            {
                $cacheExpired = true;
            }

            call_user_func_array($this->originCallback, [$cacheSlot['nodes']]);
        }

        if ($cacheHit)
        {
            if ($cacheExpired)
            {
                Logger::ins()->notice('lookup::fetching::cache::expired', ['key' => $uKey]);
            }
            else
            {
                Logger::ins()->debug('lookup::fetching::cache::hit', ['key' => $uKey]);
            }
        }
        else
        {
            Logger::ins()->debug('lookup::fetching::cache::miss', ['key' => $uKey]);

            $this->blockingCallback = $this->originCallback;
        }
    }

    /**
     * via networking
     */
    private function fetchViaNetwork()
    {
        Logger::ins()->debug('lookup::fetching::cache::ignore');

        // always fetching nodes

        $originCallback = $this->originCallback;

        $this->fetching(function ($success, $nodes = []) use ($originCallback) {

            if ($success)
            {
                call_user_func_array($originCallback, [$nodes]);
            }
            else
            {
                Observer::trigger($this->observers, Observer::EXCEPTION_WATCHER, new LookupTopicException('Lookup nodes failed (1)'));
            }

        });
    }

    /**
     * @param callable $resultCallback
     */
    private function fetching(callable $resultCallback)
    {
        $host = $this->host;
        $port = $this->port;
        $uri = $this->httpUri;

        $failedCallback = function () use ($resultCallback) {
            call_user_func_array($resultCallback, [self::ACTION_FAILED]);
        };

        DNS::resolve($host, function ($host, $ip) use ($port, $uri, $resultCallback, $failedCallback) {

            $slot = $this->getConnSlot([$host, $ip], $port);

            $slotID = $slot['id'];

            $httpClient = $slot['client'];

            Logger::ins()->debug('lookup::fetching::connSlot::got', ['slot' => $slotID, 'host' => $host, 'port' => $port, 'uri' => $uri]);

            $autoTimeoutCallback = Timeout::watch(
                $this->policy->get(Policy::LOOKUP_TIMEOUT_MS),
                function ($response) use ($slotID, $host, $port, $uri, $resultCallback) {

                    Logger::ins()->debug('lookup::fetching::result::got', ['slot' => $slotID]);

                    // nodes parsing
                    $this->parsingResult($uri, $resultCallback, $response);

                    // close immediately
                    $this->closeClient($host, $port, $slotID);

                    // release later :: 2 ms
                    Timer::after(2, function () use ($host, $port, $slotID) {
                        $this->releaseClient($host, $port, $slotID);
                    });
                }, $failedCallback);

            $httpClient->get($uri, $autoTimeoutCallback);

        }, $failedCallback, $this->policy->get(Policy::DNS_TIMEOUT_MS));
    }

    /**
     * @param $uri
     * @param callable $resultCallback
     * @param $response
     * @throws \Exception
     */
    private function parsingResult($uri, callable $resultCallback, $response)
    {
        $content = $response->body;

        Logger::ins()->debug('lookup::parsingResult::response', ['uri' => $uri, 'content' => $content]);

        $routes = json_decode($content, TRUE);

        if (isset($routes['producers']) && $routes['producers'])
        {
            call_user_func_array($resultCallback, [self::ACTION_SUCCESS, $this->exports($routes)]);
        }
        else
        {
            Logger::ins()->warning('lookup::parsingResult::empty-nodes', ['uri' => $uri]);

            Observer::trigger($this->observers, Observer::EXCEPTION_WATCHER, new LookupTopicException('Empty nodes from server'));
        }
    }

    /**
     * @param $target
     * @param $port
     * @return array
     */
    private function getConnSlot($target, $port)
    {
        list($domain, $ip) = $target;

        $id = $this->genSlotID();

        // always use new client (because it will auto close)
        $client = new \swoole_http_client($ip, $port);
        $client->setHeaders(['Host' => $domain.':'.$port, 'Accept' => 'application/vnd.nsq; version=1.0']);

        $this->httpClients[$domain][$port][$id] = $slot = ['id' => $id, 'client' => $client];

        return $slot;
    }

    /**
     * @param $host
     * @param $port
     * @param $slotID
     */
    private function closeClient($host, $port, $slotID)
    {
        if (isset($this->httpClients[$host][$port][$slotID]))
        {
            $client = $this->httpClients[$host][$port][$slotID]['client'];
            if (method_exists($client, 'close'))
            {
                Logger::ins()->debug('lookup::closeClient::doing', ['slot' => $slotID]);

                $client->close();
            }
        }
    }

    /**
     * @param $host
     * @param $port
     * @param $slotID
     */
    private function releaseClient($host, $port, $slotID)
    {
        if (isset($this->httpClients[$host][$port][$slotID]))
        {
            unset($this->httpClients[$host][$port][$slotID]);

            Logger::ins()->debug('lookup::releaseClient::done', ['slot' => $slotID]);
        }
    }

    /**
     * @param $data
     * @return array
     */
    private function exports($data)
    {
        $nodes = [];
        foreach ($data['producers'] as $producer)
        {
            $nodes[] = [
                'host' => $producer['broadcast_address'],
                'port' => [
                    'tcp' => $producer['tcp_port']
                ]
            ];
        }
        return $nodes;
    }

    /**
     * @return string
     */
    private function uniqueKey()
    {
        return implode('-', ['lookupd', $this->protocol, $this->host, $this->port, $this->topic]);
    }

    /**
     * @return int
     */
    private function genSlotID()
    {
        return ++ $this->slotID;
    }
}