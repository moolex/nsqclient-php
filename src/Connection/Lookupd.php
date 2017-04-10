<?php
/**
 * Conn for Lookupd
 * User: moyo
 * Date: 31/03/2017
 * Time: 4:53 PM
 */

namespace NSQClient\Connection;

use NSQClient\Access\Endpoint;
use NSQClient\Connection\Transport\HTTP;
use NSQClient\Exception\LookupTopicException;
use NSQClient\Logger\Logger;

class Lookupd
{
    /**
     * @var string
     */
    private static $queryFormat = '/lookup?topic=%s';

    /**
     * @var array
     */
    private static $caches = [];

    /**
     * @param Endpoint $endpoint
     * @param $topic
     * @return array
     * @throws LookupTopicException
     */
    public static function getNodes(Endpoint $endpoint, $topic)
    {
        if (isset(self::$caches[$endpoint->getUniqueID()][$topic]))
        {
            return self::$caches[$endpoint->getUniqueID()][$topic];
        }

        $url = $endpoint->getLookupd() . sprintf(self::$queryFormat, $topic);

        list($error, $result) = HTTP::get($url);

        if ($error)
        {
            list($netErrNo, $netErrMsg) = $error;
            Logger::ins()->error('Lookupd request failed', ['no' => $netErrNo, 'msg' => $netErrMsg]);
            throw new LookupTopicException($netErrMsg, $netErrNo);
        }
        else
        {
            Logger::ins()->debug('Lookupd results got', ['raw' => $result]);
            return self::$caches[$endpoint->getUniqueID()][$topic] = self::parseResult($result, $topic);
        }
    }

    /**
     * @param $rawJson
     * @param $scopeTopic
     * @return array
     */
    private static function parseResult($rawJson, $scopeTopic)
    {
        $result = json_decode($rawJson, true);

        $nodes = [];

        if (isset($result['producers']))
        {
            foreach ($result['producers'] as $producer)
            {
                $nodes[] = [
                    'topic' => $scopeTopic,
                    'host' => $producer['broadcast_address'],
                    'ports' => [
                        'tcp' => $producer['tcp_port'],
                        'http' => $producer['http_port']
                    ]
                ];
            }
        }

        return $nodes;
    }
}