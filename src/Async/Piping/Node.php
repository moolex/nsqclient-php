<?php
/**
 * NSQ Piping Node
 * User: moyo
 * Date: 4/7/16
 * Time: 12:38 PM
 */

namespace NSQClient\Async\Piping;

use NSQClient\Async\Chips\NMOpsAPI;
use NSQClient\Async\Connection\Pool;
use NSQClient\Async\Exception\SubscribeException;
use NSQClient\Async\Foundation\Buffer;
use NSQClient\Async\Foundation\Timeout;
use NSQClient\Async\Foundation\Timer;
use NSQClient\Async\Observer;
use NSQClient\Async\Policy;
use NSQClient\Contract\NMOps;
use NSQClient\Exception\GenericErrorException;
use NSQClient\Exception\InvalidMessageException;
use NSQClient\Exception\NetworkSocketException;
use NSQClient\Logger\Logger;
use NSQClient\Message\Bag as MessageBag;
use NSQClient\Message\Message;
use NSQClient\Protocol\Command;
use NSQClient\Protocol\Specification;
use NSQClient\SDK;
use Exception;

class Node implements NMOps
{
    use NMOpsAPI;

    /**
     * mod in sub
     */
    const MOD_SUB = 0;

    /**
     * mod in pub
     */
    const MOD_PUB = 1;

    /**
     * errorCallback with pool-release
     */
    const EC_WITH_RELEASE = 1;

    /**
     * @var string
     */
    private $host = '';

    /**
     * @var string
     */
    private $portTcp = '';

    /**
     * @var int
     */
    private $mode = null;

    /**
     * @var string
     */
    private $topic = '';

    /**
     * @var string
     */
    private $channel = '';

    /**
     * @var mixed
     */
    private $callback = null;

    /**
     * @var Policy
     */
    private $policy = null;

    /**
     * @var array
     */
    private $observers = [];

    /**
     * @var int
     */
    private $slotID = null;

    /**
     * @var object
     */
    private $connection = null;

    /**
     * @var bool
     */
    private $connected = false;

    /**
     * @var int
     */
    private $retryLaterSeconds = 3;

    /**
     * @var int
     */
    private $pubIdlingTimeout = 0;

    /**
     * @var int
     */
    private $subClosingTimeout = 0;

    /**
     * @var int
     */
    private $lastPublishTime = 0;

    /**
     * @var array
     */
    private $stashing = [];

    /**
     * @var bool
     */
    private $clientIdentified = false;

    /**
     * @var bool
     */
    private $subClientReady = false;

    /**
     * @var bool
     */
    private $cIsWaitingResult = false;

    /**
     * @var bool
     */
    private $cIsWaitingClosed = false;

    /**
     * @var mixed
     */
    private $waitTimeoutWatcher = null;

    /**
     * Node constructor.
     * @param string $host
     * @param array $ports
     * @param int $mode
     * @param mixed $topic
     * @param Policy $policy
     * @param int $slotID
     */
    public function __construct($host, array $ports, $mode, $topic, Policy $policy, $slotID = null)
    {
        $this->host = $host;
        $this->portTcp = $ports['tcp'];

        if (in_array($mode, [self::MOD_SUB, self::MOD_PUB]))
        {
            $this->mode = $mode;
            // set
            $this->topic = $topic;
            // but
            if ($this->mode == self::MOD_SUB)
            {
                list($this->topic, $this->channel) = $topic;
            }
        }
        else
        {
            throw new GenericErrorException('Illegal node MOD');
        }

        $this->policy = $policy;

        $this->slotID = $slotID;

        $this->refreshClient();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return sprintf('S[%d]@%s:%d/%s', $this->slotID, $this->host, $this->portTcp, $this->topic);
    }

    /**
     * @param $observers
     * @return $this
     */
    public function setObservers(array $observers)
    {
        $this->observers = $observers;

        return $this;
    }

    /**
     * @param $message
     * @param callable $poolCallback
     */
    public function publish($message, callable $poolCallback)
    {
        $this->callback = $poolCallback;

        try
        {
            if ($message instanceof Message)
            {
                // one msg pub
                $buffer = Command::message($this->topic, $message->data(), $message->deferred());
            }
            else if ($message instanceof MessageBag)
            {
                // bulk msg pub
                $buffer = Command::messages($this->topic, $message->export());
            }
            else
            {
                throw new InvalidMessageException('Illegal message object');
            }
        }
        catch (Exception $e)
        {
            Observer::trigger($this->observers, Observer::EXCEPTION_WATCHER, $e);
            return;
        }

        if ($this->connected)
        {
            $this->pipeWriting($buffer);
            $this->waitingResult();
        }
        else
        {
            $this->stashAppend($buffer);

            Logger::ins()->debug('node::publish::stash::append', ['slot' => $this->slotID]);
        }

        $this->lastPublishTime = time();
    }

    /**
     * @param callable $callback
     */
    public function subscribe(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * recycle timer (only in pub mod)
     * @param $seconds
     */
    public function idleRecycle($seconds)
    {
        $this->pubIdlingTimeout = $seconds;
        Timer::after($seconds * 1000, [$this, 'handleIdleTimer']);
    }

    /**
     * close timer (only in sub mod)
     * @param $seconds
     */
    public function closeAfter($seconds)
    {
        if (is_numeric($seconds) && $seconds > 0)
        {
            $this->subClosingTimeout = $seconds;
            Timer::after($seconds * 1000, [$this, 'handleCloseTimer']);
        }
    }

    /**
     * manual close
     */
    public function close()
    {
        $this->handleCloseTimer();
    }

    /**
     * init some variables
     */
    private function varInitialize()
    {
        $this->connected(false);
        $this->clientIdentified = false;
        $this->subClientReady = false;
    }

    /**
     * refresh client
     * @param $laterSeconds
     */
    private function refreshClient($laterSeconds = 0)
    {
        $refProcess = function ()
        {
            $this->varInitialize();
            $this->releaseClient();
            $this->applyClient();
            $this->registerEvents();
            $this->tryConnect();
        };

        if ($laterSeconds)
        {
            Timer::after($laterSeconds * 1000, $refProcess);
        }
        else
        {
            call_user_func($refProcess);
        }
    }

    /**
     * new client
     */
    private function applyClient()
    {
        $this->connection = new \swoole_client(SWOOLE_TCP, SWOOLE_SOCK_ASYNC);

        $this->connection->set([
            'open_length_check' => 1,
            'package_length_type' => 'N',
            'package_length_offset' => 0,
            'package_body_offset' => 4,
            'package_max_length'  => 8192000 + 1024, // plus protocol size
        ]);

        Logger::ins()->info('node::network-client::created', ['slot' => $this->slotID]);
    }

    /**
     * remove client
     */
    private function releaseClient()
    {
        unset($this->connection);
    }

    /**
     * set callback
     */
    private function registerEvents()
    {
        $this->connection->on('connect', [$this, 'handleConnected']);
        $this->connection->on('receive', [$this, 'handleReceived']);
        $this->connection->on('error', [$this, 'handleError']);
        $this->connection->on('close', [$this, 'handleClosing']);
    }

    /**
     * do connect
     */
    private function tryConnect()
    {
        $port = $this->portTcp;

        DNS::resolve($this->host, function ($host, $ip) use ($port) {

            $this->connection->connect($ip, $port);

            Logger::ins()->debug('node::network::establishing', ['slot' => $this->slotID, 'host' => $host, 'ip' => $ip]);

        }, function () {

            $this->errorCallback('E_DNS_RESOLVE_FAILED', self::EC_WITH_RELEASE);

        }, $this->policy->get(Policy::DNS_TIMEOUT_MS));
    }

    /**
     * @param $set
     *
     * @return bool
     */
    private function connected($set = null)
    {
        if (is_bool($set))
        {
            $this->connected = $set;
        }
        return $this->connected;
    }

    /**
     * append buffer to stash (because client not connected)
     * @param $buffer
     */
    private function stashAppend($buffer)
    {
        $this->stashing[] = $buffer;
    }

    /**
     * release stash buffers (write to pipe directed)
     */
    private function stashRelease()
    {
        if ($this->stashing)
        {
            while (null !== $buffer = array_shift($this->stashing))
            {
                $this->pipeWriting($buffer);
                $this->waitingResult();
            }
        }
    }

    /**
     * set node is result-waiting when we connected and ready (in publish)
     */
    private function waitingResult()
    {
        $this->cIsWaitingResult = true;

        // set wait-timeout watcher

        $this->waitTimeoutWatcher = Timeout::watch($this->policy->get(Policy::PUB_TIMEOUT_MS), function () {

            return Timeout::SIG_CALLBACK_REACHED;

        }, function() {

            $this->errorCallback('E_WAIT_TIMEOUT', self::EC_WITH_RELEASE);

        });
    }

    /**
     * set node is call-waiting when we processed an action (in publish)
     */
    private function waitingNextCall()
    {
        $this->cIsWaitingResult = false;

        // clear wait-timeout watcher

        if (is_callable($this->waitTimeoutWatcher))
        {
            $timeoutSIG = call_user_func($this->waitTimeoutWatcher);

            $this->waitTimeoutWatcher = null;

            if ($timeoutSIG === Timeout::SIG_TIMEOUT_REACHED)
            {
                return false;
            }
        }

        return true;
    }

    /**
     * set node is close-waiting if we want disconnect from server
     */
    private function waitingClosed()
    {
        $this->cIsWaitingClosed = true;
    }

    /**
     * check idle time of last pub
     */
    public function handleIdleTimer()
    {
        if (time() - $this->lastPublishTime > $this->pubIdlingTimeout)
        {
            $this->pipeClosing();

            Logger::ins()->info('node::idle-timer::handled', ['slot' => $this->slotID]);
        }
        else
        {
            // schedule next check
            $this->idleRecycle($this->pubIdlingTimeout);
        }
    }

    /**
     * close tcp conn
     */
    public function handleCloseTimer()
    {
        $this->waitingClosed();
        $this->pipeWriting(Command::close());

        Logger::ins()->info('node::close-timer::handled', ['slot' => $this->slotID]);
    }

    /**
     * when connected to server
     */
    public function handleConnected()
    {
        Logger::ins()->info('node::network::established', ['slot' => $this->slotID]);

        $this->connected(true);

        $this->pipeWriting(Command::magic());
        $this->pipeWriting(Command::identify(getmypid(), gethostname(), sprintf('%s/%s', SDK::NAME, SDK::VERSION)));

        if ($this->mode == self::MOD_SUB)
        {
            Observer::trigger($this->observers, Observer::SUB_STATE_WATCHER, ['event' => 'connected', 'slot' => $this->slotID]);
        }
    }

    /**
     * when connection closing
     */
    public function handleClosing()
    {
        Logger::ins()->info('node::conn::closing', ['slot' => $this->slotID]);

        $this->connected(false);

        if ($this->mode == self::MOD_SUB)
        {
            if (false === $this->cIsWaitingClosed)
            {
                Logger::ins()->warning('node::conn::lost', ['slot' => $this->slotID]);

                $this->refreshClient($this->retryLaterSeconds);
            }
            else
            {
                Observer::trigger($this->observers, Observer::SUB_STATE_WATCHER, ['event' => 'closed', 'slot' => $this->slotID]);
            }
        }
        else
        {
            $this->errorCallback('E_NETWORK_CLOSED', self::EC_WITH_RELEASE);
        }
    }

    /**
     * when connection has error
     */
    public function handleError()
    {
        Logger::ins()->warning('node::conn::broken', ['slot' => $this->slotID]);

        $this->connected(false);

        if ($this->mode == self::MOD_SUB)
        {
            $this->refreshClient($this->retryLaterSeconds);
        }
        else
        {
            $this->errorCallback('E_NETWORK_ERROR', self::EC_WITH_RELEASE);
        }
    }

    /**
     * when we received data from server
     * @param $_
     * @param $data
     */
    public function handleReceived($_, $data)
    {
        Logger::ins()->debug('node::stream::recv', ['slot' => $this->slotID, 'data' => $data]);

        try
        {
            $buffer = new Buffer($data);
            while ($buffer->readable())
            {
                Frame::processing(Specification::readFrame($buffer), $this);
            }
            unset($buffer);
        }
        catch (Exception $e)
        {
            Observer::trigger($this->observers, Observer::EXCEPTION_WATCHER, $e);
        }
    }

    /**
     * @param $id
     */
    public function cmdFIN($id)
    {
        $this->pipeWriting(Command::finish($id));
    }

    /**
     * @param $id
     * @param $millisecond
     */
    public function cmdREQ($id, $millisecond)
    {
        $this->pipeWriting(Command::requeue($id, $millisecond));
    }

    /**
     * @param $data
     * @return bool
     */
    public function pipeWriting($data)
    {
        $sent = $this->connection->send($data);

        if ($sent > 0)
        {
            Logger::ins()->debug('node::stream::send', ['slot' => $this->slotID, 'data' => $data]);

            return true;
        }
        else
        {
            Logger::ins()->error('node::network::sending::failed', ['slot' => $this->slotID, 'sent' => $sent]);

            Observer::trigger($this->observers, Observer::EXCEPTION_WATCHER, new NetworkSocketException('Connection sent failed'));

            return false;
        }
    }

    /**
     * close connection
     */
    public function pipeClosing()
    {
        $this->connection->close();
    }

    /**
     * rps is msg
     * @param Message $msg
     */
    public function msgCallback(Message $msg)
    {
        if ($this->cIsWaitingClosed)
        {
            $msg->retry();
        }
        else
        {
            call_user_func_array($this->callback, [$msg]);
        }
    }

    /**
     * rps is ok
     */
    public function okCallback()
    {
        if ($this->clientIdentified)
        {
            if ($this->pubIsWaitingResult())
            {
                if ($this->waitingNextCall())
                {
                    call_user_func_array($this->callback, [['result' => 'ok', 'error' => ''], $this->slotID]);
                }
                else
                {
                    Logger::ins()->error('node::publish::ok-timeout::conflict', ['slot' => $this->slotID]);
                }
            }
            else if ($this->mode == self::MOD_SUB)
            {
                if ($this->subClientReady)
                {
                    Logger::ins()->info('node::subscribe::ok-msg::surplus', ['slot' => $this->slotID]);
                }
                else
                {
                    // we received second "OK" for client ready

                    $this->subClientReady = true;

                    Logger::ins()->debug('node::subscribe::client::ready', ['slot' => $this->slotID]);
                }
            }
            else
            {
                Logger::ins()->notice('node::ok-msg::dispatch::confuse', ['slot' => $this->slotID]);
            }
        }
        else
        {
            // we received first "OK" for client identify

            $this->clientIdentified = true;

            if ($this->mode == self::MOD_SUB)
            {
                $this->pipeWriting(Command::subscribe($this->topic, $this->channel));
                $this->pipeWriting(Command::ready(1));
            }

            if ($this->mode == self::MOD_PUB)
            {
                $this->stashRelease();

                Logger::ins()->debug('node::publish::stash::flushed', ['slot' => $this->slotID]);
            }
        }
    }

    /**
     * rps is error
     * @param $error
     * @param $policy
     */
    public function errorCallback($error, $policy = null)
    {
        if ($this->pubIsWaitingResult())
        {
            if ($this->waitingNextCall())
            {
                call_user_func_array($this->callback, [['result' => 'error', 'error' => $error], $this->slotID]);
            }
            else
            {
                Logger::ins()->error('node::publish::error-timeout::conflict', ['slot' => $this->slotID]);
            }

            // policy actions

            if ($policy === self::EC_WITH_RELEASE)
            {
                Pool::release($this->slotID);
            }
        }
        else if ($this->mode == self::MOD_SUB)
        {
            Logger::ins()->error('node::subscribe::err-info::got', ['slot' => $this->slotID, 'error' => $error]);

            Observer::trigger($this->observers, Observer::EXCEPTION_WATCHER, new SubscribeException($error));
        }
        else
        {
            Logger::ins()->error('node::err-info::dispatch::confuse', ['slot' => $this->slotID, 'error' => $error]);
        }
    }

    /**
     * @return bool
     */
    private function pubIsWaitingResult()
    {
        return $this->mode == self::MOD_PUB && ($this->cIsWaitingResult === true || $this->connected === false);
    }
}