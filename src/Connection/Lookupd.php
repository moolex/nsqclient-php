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

class Lookupd
{
    /**
     * @var string
     */
    private static $queryFormat = '/lookup?topic=%s';

    /**
     * @param Endpoint $endpoint
     * @param $topic
     * @return array
     * @throws LookupTopicException
     */
    public static function getNodes(Endpoint $endpoint, $topic)
    {
        $url = $endpoint->getLookupd() . sprintf(self::$queryFormat, $topic);

        list($error, $result) = HTTP::get($url);

        if ($error)
        {
            list($netErrNo, $netErrMsg) = $error;
            throw new LookupTopicException($netErrMsg, $netErrNo);
        }
        else
        {
            return self::parseResult($result, $topic);
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