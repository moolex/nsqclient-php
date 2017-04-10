<?php
/**
 * Logger via echo
 * User: moyo
 * Date: 10/04/2017
 * Time: 12:07 PM
 */

namespace NSQClient\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

class EchoLogger extends AbstractLogger
{
    /**
     * @var array
     */
    private $levels = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
        LogLevel::WARNING,
        LogLevel::NOTICE,
        LogLevel::INFO,
        LogLevel::DEBUG,
    ];

    /**
     * @var array
     */
    private $colors = [
        LogLevel::EMERGENCY => '0;31m', // red
        LogLevel::ALERT => '0;31m', // red
        LogLevel::CRITICAL => '0;31m', // red
        LogLevel::ERROR => '0;31m', // red
        LogLevel::WARNING => '1;33m', // yellow
        LogLevel::NOTICE => '0;35m', // purple
        LogLevel::INFO => '0;36m', // cyan
        LogLevel::DEBUG => '0;32m', // green
    ];

    /**
     * @var string
     */
    private $colorCtxKey = '0;37m'; // light gray

    /**
     * @var string
     */
    private $colorMsg = '1;37m'; // white

    /**
     * @var string
     */
    private $colorNO = "\033[0m";

    /**
     * @var string
     */
    private $colorBGN = "\033[";

    /**
     * @var array
     */
    private $allows = [];

    /**
     * EchoLogger constructor.
     * @param string $minimalLevel
     */
    public function __construct($minimalLevel = LogLevel::NOTICE)
    {
        $this->allows = array_slice($this->levels, 0, array_search($minimalLevel, $this->levels, true) + 1);
    }

    /**
     * @param mixed $level
     * @param string $message
     * @param array $context
     */
    public function log($level, $message, array $context = array())
    {
        if (in_array($level, $this->allows))
        {
            printf('[%s]%s[%s] : %s ~ %s %s',
                $this->printableLevel($level),
                " ",
                date('Y-m-d H:i:s'),
                $this->printableMessage($message),
                $this->printableContext($context),
                "\n"
            );
        }
    }

    /**
     * @param $level
     * @return string
     */
    private function printableLevel($level)
    {
        return $this->colorBGN . $this->colors[$level] . strtoupper($level) . $this->colorNO;
    }

    /**
     * @param $message
     * @return string
     */
    private function printableMessage($message)
    {
        return $this->colorBGN . $this->colorMsg . $message . $this->colorNO;
    }

    /**
     * @param $context
     * @return string
     */
    private function printableContext($context)
    {
        $print = '[';

        array_walk($context, function ($item, $key) use (&$print) {
            $ctx = $this->colorBGN . $this->colorCtxKey . $key . $this->colorNO . '=';
            if (is_array($item))
            {
                $ctx .= json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            else
            {
                $ctx .= $item;
            }
            $print .= $ctx . ',';
        });

        return $print . ']';
    }
}