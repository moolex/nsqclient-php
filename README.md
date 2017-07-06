# NSQClient

Yet another PHP client for [NSQ](http://nsq.io)

### Installation (via composer)

```
composer require moolex/nsqclient dev-master
```

### Usage

#### Publish

```php
$topic = 'my_topic';
$endpoint = new \NSQClient\Access\Endpoint('http://127.0.0.1:4161');
$message = new \NSQClient\Message\Message('hello world');
$result = \NSQClient\Queue::publish($endpoint, $topic, $message);
```

#### Publish (deferred)

```php
$topic = 'my_topic';
$endpoint = new \NSQClient\Access\Endpoint('http://127.0.0.1:4161');
$message = (new \NSQClient\Message\Message('hello world'))->deferred(5);
$result = \NSQClient\Queue::publish($endpoint, $topic, $message);
```

#### Publish (batch)

```php
$topic = 'my_topic';
$endpoint = new \NSQClient\Access\Endpoint('http://127.0.0.1:4161');
$message = \NSQClient\Message\Bag::generate(['msg data 1', 'msg data 2']);
$result = \NSQClient\Queue::publish($endpoint, $topic, $message);
```

#### Subscribe

```php
$topic = 'my_topic';
$channel = 'my_channel';
$endpoint = new \NSQClient\Access\Endpoint('http://127.0.0.1:4161');
\NSQClient\Queue::subscribe($endpoint, $topic, $channel, function (\NSQClient\Contract\Message $message) {
    echo 'GOT ', $message->id(), "\n";
    // make done
    $message->done();
    // make retry immediately
    // $message->retry();
    // make retry delayed in 10 seconds
    // $message->delay(10);
});
```
