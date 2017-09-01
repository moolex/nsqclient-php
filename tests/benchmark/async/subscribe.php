<?php
/**
 * subscribe test
 * User: moyo
 * Date: 01/09/2017
 * Time: 4:48 PM
 */

namespace BMTests\Subscribe
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

    $opts = getopt('l:t:h:c:r:');
    if (count($opts) === 5)
    {
        $lookupd = $opts['l'];
        $topic = $opts['t'];
        $channel = $opts['h'];
        $concurrency = $opts['c'];
        $requests = $opts['r'];
    }
    else
    {
        exit('Usage: ./subscribe.php -l http://127.0.0.1:4161 -t topic -h channel -c 20 -r 30s');
    }

    //SDK::setLogger(new EchoLogger(LogLevel::DEBUG));

    $ep = new Endpoint($lookupd);
    $ep->setAsynced(new Asynced(), new Policy());

    $fw = new Framework();
    $fw->initSubscribeTask($ep, $topic, $channel, $concurrency, $requests);
}