<?php
/**
 * Connection node for nsqd
 * User: moyo
 * Date: 31/03/2017
 * Time: 4:37 PM
 */

namespace NSQClient\Connection;

use NSQClient\Access\Endpoint;
use NSQClient\Connection\Transport\HTTP;
use NSQClient\Connection\Transport\TCP;
use NSQClient\Contract\Network\Stream;
use NSQClient\Exception\GenericErrorException;
use NSQClient\Exception\InvalidMessageException;
use NSQClient\Exception\NetworkSocketException;
use NSQClient\Exception\UnknownProtocolException;
use NSQClient\Logger\Logger;
use NSQClient\Message\Bag as MessageBag;
use NSQClient\Message\Message;
use NSQClient\Protocol\Command;
use NSQClient\Protocol\CommandHTTP;
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
     * PUB: Idle seconds before recycling
     * SUB: Run seconds before exiting
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
     * @param $topic
     * @return self
     */
    public function setTopic($topic)
    {
        $this->topic = $topic;

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
     * @return self
     */
    public function setProducer()
    {
        if ($this->connTCP)
        {
            $this->connTCP->setRecycling($this->lifecycle);
        }

        return $this;
    }

    /**
     * @param callable $processor
     * @return self
     */
    public function setConsumer(callable $processor)
    {
        $this->subProcessor = $processor;

        if ($this->lifecycle)
        {
            $nsqd = $this;
            Pool::getEvLoop()->addTimer($this->lifecycle, function () use ($nsqd) {
                $nsqd->closing();
            });
        }

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
     * @return bool
     */
    public function isConsumer()
    {
        return ! is_null($this->subProcessor);
    }

    /**
     * @param Stream $stream
     */
    public function handshake(Stream $stream)
    {
        $stream->write(Command::magic());
    }

    /**
     * @param $message
     * @return bool
     */
    public function publish($message)
    {
        return $this->endpoint->getConnType() == 'tcp' ? $this->publishViaTCP($message) : $this->publishViaHTTP($message);
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

        Pool::setEvAttached();

        Logger::ins()->debug('Consumer is ready', $this->loggingMeta());
    }

    /**
     * @param $messageID
     */
    public function finish($messageID)
    {
        Logger::ins()->debug('Make message is finished', $this->loggingMeta(['id' => $messageID]));
        $this->connTCP->write(Command::finish($messageID));
    }

    /**
     * @param $messageID
     * @param $millisecond
     */
    public function requeue($messageID, $millisecond)
    {
        Logger::ins()->debug('Make message is requeued', $this->loggingMeta(['id' => $messageID, 'delay' => $millisecond]));
        $this->connTCP->write(Command::requeue($messageID, $millisecond));
    }

    /**
     * subscribe closing
     */
    public function closing()
    {
        Logger::ins()->info('Consumer is closing', $this->loggingMeta());
        $this->connTCP->write(Command::close());
    }

    /**
     * process exiting
     */
    private function exiting()
    {
        Logger::ins()->info('Consumer is exiting', $this->loggingMeta());
        $this->connTCP->close();
        Pool::setEvDetached();
    }

    /**
     * @param $message
     * @return bool
     */
    private function publishViaHTTP($message)
    {
        if ($message instanceof Message)
        {
            list($uri, $data) = CommandHTTP::message($this->topic, $message->data());
        }
        else if ($message instanceof MessageBag)
        {
            list($uri, $data) = CommandHTTP::messages($this->topic, $message->export());
        }
        else
        {
            Logger::ins()->error('Un-expected pub message', $this->loggingMeta(['input' => json_encode($message)]));
            throw new InvalidMessageException('Unknowns message object');
        }

        list($error, $result) = HTTP::post(sprintf('http://%s:%d/%s', $this->host, $this->portHTTP, $uri), $data);

        if ($error)
        {
            list($netErrNo, $netErrMsg) = $error;
            Logger::ins()->error('HTTP Publish is failed', $this->loggingMeta(['no' => $netErrNo, 'msg' => $netErrMsg]));
            throw new NetworkSocketException($netErrMsg, $netErrNo);
        }
        else
        {
            return $result === 'OK' ? true : false;
        }
    }

    /**
     * @param $message
     * @return bool
     */
    private function publishViaTCP($message)
    {
        if ($message instanceof Message)
        {
            $buffer = Command::message($this->topic, $message->data(), $message->deferred());
        }
        else if ($message instanceof MessageBag)
        {
            $buffer = Command::messages($this->topic, $message->export());
        }
        else
        {
            Logger::ins()->error('Un-expected pub message', $this->loggingMeta(['input' => json_encode($message)]));
            throw new InvalidMessageException('Unknowns message object');
        }

        $this->connTCP->write($buffer);

        do
        {
            $result = $this->dispatching(Specification::readFrame($this->connTCP));
        }
        while (is_null($result));

        return $result;
    }

    /**
     * @param $frame
     * @return bool|null
     */
    private function dispatching($frame)
    {
        switch (true)
        {
            case Specification::frameIsOK($frame):
                return true;
                break;
            case Specification::frameIsMessage($frame):
                Logger::ins()->debug('FRAME got is message', $this->loggingMeta(['id' => $frame['id'], 'data' => $frame['payload']]));
                $this->processingMessage(
                    new Message(
                        $frame['payload'],
                        $frame['id'],
                        $frame['attempts'],
                        $frame['timestamp'],
                        $this
                    )
                );
                return null;
                break;
            case Specification::frameIsHeartbeat($frame):
                Logger::ins()->debug('FRAME got is heartbeat', $this->loggingMeta());
                $this->connTCP->write(Command::nop());
                return null;
                break;
            case Specification::frameIsError($frame):
                Logger::ins()->error('FRAME got is error', $this->loggingMeta(['error' => $frame['error']]));
                throw new GenericErrorException($frame['error']);
                break;
            case Specification::frameIsBroken($frame):
                Logger::ins()->warning('FRAME got is broken', $this->loggingMeta(['error' => $frame['error']]));
                throw new GenericErrorException($frame['error']);
                break;
            case Specification::frameIsCloseWait($frame):
                Logger::ins()->debug('FRAME got is close-wait', $this->loggingMeta());
                $this->exiting();
                return null;
                break;
            default:
                Logger::ins()->warning('FRAME got is unknowns', $this->loggingMeta());
                throw new UnknownProtocolException('Unknowns protocol data ('.json_encode($frame).')');
        }
    }

    /**
     * @param Message $message
     */
    private function processingMessage(Message $message)
    {
        try
        {
            call_user_func_array($this->subProcessor, [$message]);
        }
        catch (\Exception $exception)
        {
            // TODO add observer for usr callback
            Logger::ins()->critical('Consuming processor has exception', $this->loggingMeta([
                'cls' => get_class($exception),
                'msg' => $exception->getMessage()
            ]));
        }
    }

    /**
     * @param $extra
     * @return array
     */
    private function loggingMeta($extra = [])
    {
        return array_merge([
            'topic' => $this->topic,
            'host' => $this->host,
            'port-tcp' => $this->portTCP
        ], $extra);
    }
}