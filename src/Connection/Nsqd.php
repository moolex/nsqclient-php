<?php
/**
 * Connection node for nsqd
 * User: moyo
 * Date: 31/03/2017
 * Time: 4:37 PM
 */

namespace NSQClient\Connection;

use NSQClient\Access\Endpoint;
use NSQClient\Connection\Transport\TCP;
use NSQClient\Contract\Network\Stream;
use NSQClient\Exception\GenericErrorException;
use NSQClient\Exception\UnknownProtocolException;
use NSQClient\Protocol\Command;
use NSQClient\Protocol\Specification;
use NSQClient\SDK;

class Nsqd
{
    /**
     * @var Endpoint
     */
    private $endpoint = null;

    /**
     * @var string
     */
    private $host = '127.0.0.1';

    /**
     * @var int
     */
    private $portTCP = 4150;

    /**
     * @var TCP
     */
    private $connTCP = null;

    /**
     * @var int
     */
    private $portHTTP = 4151;

    /**
     * @var string
     */
    private $topic = 'topic';

    /**
     * @var int
     */
    private $lifecycle = 0;

    /**
     * @var callable
     */
    private $subProcessor = null;

    /**
     * Nsqd constructor.
     * @param Endpoint $endpoint
     */
    public function __construct(Endpoint $endpoint)
    {
        $this->endpoint = $endpoint;

        if ($this->endpoint->getConnType() == 'tcp')
        {
            $this->connTCP = new TCP;
            $this->connTCP->setHandshake([$this, 'handshake']);
        }
    }

    /**
     * @param $route
     * @return self
     */
    public function setRoute($route)
    {
        $this->topic = $route['topic'];

        $this->host = $route['host'];
        $this->portTCP = $route['ports']['tcp'];
        $this->portHTTP = $route['ports']['http'];

        if ($this->connTCP)
        {
            $this->connTCP->setTarget($this->host, $this->portTCP);
        }

        return $this;
    }

    /**
     * @param $seconds
     * @return self
     */
    public function setLifecycle($seconds)
    {
        $this->lifecycle = $seconds;

        return $this;
    }

    /**
     * @param callable $processor
     * @return self
     */
    public function setProcessor(callable $processor)
    {
        $this->subProcessor = $processor;

        return $this;
    }

    /**
     * @return int
     */
    public function getSockID()
    {
        return (int)$this->connTCP->socket();
    }

    /**
     * @return Stream
     */
    public function getSockIns()
    {
        return $this->connTCP;
    }

    /**
     * @param Stream $stream
     */
    public function handshake(Stream $stream)
    {
        $stream->write(Command::magic());
    }

    /**
     * @param $channel
     */
    public function subscribe($channel)
    {
        $this->connTCP->setBlocking(false);

        $evLoop = Pool::getEvLoop();

        $evLoop->addReadStream($this->connTCP->socket(), function ($socket) {
            $this->dispatching(Specification::readFrame(Pool::search($socket)));
        });

        $this->connTCP->write(Command::identify(getmypid(), gethostname(), sprintf('%s/%s', SDK::NAME, SDK::VERSION)));
        $this->connTCP->write(Command::subscribe($this->topic, $channel));
        $this->connTCP->write(Command::ready(1));
    }

    /**
     * @param $frame
     * @return bool
     */
    private function dispatching($frame)
    {
        switch (true)
        {
            case Specification::frameIsOK($frame):
                return true;
                break;
            case Specification::frameIsMessage($frame):
                var_dump($frame);
                break;
            case Specification::frameIsHeartbeat($frame):
                $this->connTCP->write(Command::nop());
                break;
            case Specification::frameIsError($frame):
                throw new GenericErrorException($frame['error']);
                break;
            case Specification::frameIsBroken($frame):
                throw new GenericErrorException($frame['error']);
                break;
            case Specification::frameIsCloseWait($frame):
                // TODO safety to exit process
                break;
            default:
                throw new UnknownProtocolException('Unknown protocol data ('.json_encode($frame).')');
        }

        return false;
    }
}