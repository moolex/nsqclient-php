<?php
/**
 * publish test
 * User: moyo
 * Date: 01/09/2017
 * Time: 4:47 PM
 */

namespace BMTests\Publish
{
    use BMTests\Framework;
    use NSQClient\Access\Asynced;
    use NSQClient\Access\Endpoint;
    use NSQClient\Async\Policy;
    use NSQClient\Logger\EchoLogger;
    use NSQClient\SDK;
    use Psr\Log\LogLevel;

    require '../../../vendor/autoload.php';
    require 'framework.php';

    $opts = getopt('l:t:c:r:i:');
    if (count($opts) === 5)
    {
        $lookupd = $opts['l'];
        $topic = $opts['t'];
        $concurrency = $opts['c'];
        $requests = $opts['r'];
        $interval = $opts['i'];
    }
    else
    {
        exit('Usage: ./publish.php -l http://127.0.0.1:4161 -t topic -c 20 -r 30s -i 2');
    }

    //SDK::setLogger(new EchoLogger(LogLevel::DEBUG));

    $ep = new Endpoint($lookupd);
    $ep->setAsynced(new Asynced(), new Policy());

    $fw = new Framework();
    $fw->initPublishTask($ep, $topic, $concurrency, $requests, $interval);
}