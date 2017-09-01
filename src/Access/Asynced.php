<?php
/**
 * Asynced support
 * User: moyo
 * Date: 01/09/2017
 * Time: 11:55 AM
 */

namespace NSQClient\Access;

class Asynced
{
    /**
     * @var callable
     */
    private $pubExceptionWatcher = null;

    /**
     * @var callable
     */
    private $subExceptionWatcher = null;

    /**
     * @var callable
     */
    private $subStateWatcher = null;

    /**
     * @param callable $watcher
     * @return self
     */
    public function setPubExceptionWatcher(callable $watcher)
    {
        $this->pubExceptionWatcher = $watcher;
        return $this;
    }

    /**
     * @return callable
     */
    public function getPubExceptionWatcher()
    {
        return $this->pubExceptionWatcher;
    }

    /**
     * @param callable $watcher
     * @return self
     */
    public function setSubExceptionWatcher(callable $watcher)
    {
        $this->subExceptionWatcher = $watcher;
        return $this;
    }

    /**
     * @return callable
     */
    public function getSubExceptionWatcher()
    {
        return $this->subExceptionWatcher;
    }

    /**
     * @param callable $watcher
     * @return self
     */
    public function setSubStateWatcher(callable $watcher)
    {
        $this->subStateWatcher = $watcher;
        return $this;
    }

    /**
     * @return callable
     */
    public function getSubStateWatcher()
    {
        return $this->subStateWatcher;
    }
}