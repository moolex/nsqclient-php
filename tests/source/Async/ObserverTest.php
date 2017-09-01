<?php
/**
 * NSQ Observer manager
 * User: moyo
 * Date: 8/29/16
 * Time: 5:36 PM
 */

namespace NSQClient\Async\Tests\Foundation;

use NSQClient\Async\Observer;

class ObserverTest extends \PHPUnit_Framework_TestCase
{
    private $exceptionWatched = false;

    private $subStateWatched = false;

    public function test_trigger()
    {
        $observers = [
            Observer::EXCEPTION_WATCHER => [$this, 'exceptionWatcher'],
            Observer::SUB_STATE_WATCHER => [$this, 'subStateWatcher']
        ];

        Observer::trigger($observers, Observer::EXCEPTION_WATCHER, null);
        Observer::trigger($observers, Observer::SUB_STATE_WATCHER, null);

        $this->assertEquals(true, $this->exceptionWatched);
        $this->assertEquals(true, $this->subStateWatched);
    }

    public function exceptionWatcher()
    {
        $this->exceptionWatched = true;
    }

    public function subStateWatcher()
    {
        $this->subStateWatched = true;
    }
}