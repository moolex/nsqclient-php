<?php
/**
 * foundation base
 * User: moyo
 * Date: 01/09/2017
 * Time: 4:48 PM
 */

namespace BMTests
{
    use NSQClient\Access\Endpoint;
    use NSQClient\Async\Foundation\Timer;
    use NSQClient\Message\Message;
    use NSQClient\Queue;

    class Framework
    {
        const MOD_IN_REQUESTS = 1;

        const MOD_IN_SECONDS = 2;

        const TARGET_CHECKING_RT = 1;

        const TARGET_CHECKING_DIM = 0;

        private $testingCounts = 0;

        private $successCounts = 0;

        private $failedCounts = 0;

        private $failedMsgTops = [];

        private $exceptionCounts = 0;

        private $exceptionMsgTops = [];

        private $testingTopic = '';

        private $subConnects = 0;

        private $taskBeginTime = 0;

        private $jobConcurrency = 0;

        private $targetMode = null;

        private $targetRequests = 0;

        private $targetSeconds = 0;

        private $monitorTimerKey = 'mgr_monitor';

        private $monitorLastRun = 0;

        private $monitorRunSeconds = 0;

        private $monitorLastCounts = 0;

        private $monitorLastNonSpeedTime = 0;

        private $monitorHighestSpeed = 0;

        private $monitorHighestMemory = 0;

        public function __construct()
        {
            Timer::loop($this->monitorTimerKey, 3 * 1000, function () {

                $this->monitorReports();

            });
        }

        public function initPublishTask(Endpoint $endpoint, $topic, $concurrency, $requests, $intervalMS)
        {
            $endpoint->getAsynced()->setPubExceptionWatcher(function (\Exception $e) {

                $this->comResultCount(['result' => 'exception', 'msg' => $e->getMessage()]);

            });

            $this->testingTopic = $topic;

            $this->taskBeginTime = time();

            $this->jobConcurrency = $concurrency;

            $this->detectTargetMode($requests);

            for ($jobID = 0; $jobID < $concurrency; $jobID ++)
            {
                $uKey = 'cc_'.$jobID;

                Timer::loop($uKey, $intervalMS, function () use ($uKey, $endpoint) {

                    if ($this->checkTargetIsReached(self::TARGET_CHECKING_DIM))
                    {
                        Timer::stop($uKey);
                        return;
                    }
                    else
                    {
                        $this->testingCounts ++;

                        Queue::publish($endpoint, $this->testingTopic, new Message('msg_content_'.(string)microtime(true)), function ($result) {

                            $this->comResultCount($result);

                        });

                    }

                });
            }
        }

        public function initSubscribeTask(Endpoint $endpoint, $topic, $channel, $concurrency, $requests)
        {
            $endpoint
                ->getAsynced()
                ->setSubExceptionWatcher(function (\Exception $e) {

                    $this->testingCounts ++;

                    $this->comResultCount(['result' => 'exception', 'msg' => $e->getMessage()]);

                })
                ->setSubStateWatcher(function ($state) {

                    switch ($state['event'])
                    {
                        case 'connected':
                            $this->subConnects ++;
                            break;
                        case 'closed':
                            $this->subConnects --;
                            break;
                    }

                    if ($this->subConnects < 1 && !$this->checkTargetIsReached(self::TARGET_CHECKING_RT))
                    {
                        echo '!!! - SUB connects not enough (Target not reached) - !!!', "\n";
                    }

                })
            ;

            $this->testingTopic = $topic;

            $this->taskBeginTime = time();

            $this->jobConcurrency = $concurrency;

            $this->detectTargetMode($requests);

            if (!$this->targetSeconds)
            {
                $this->halt('ERROR: target seconds not correct');
            }

            for ($jobID = 0; $jobID < $concurrency; $jobID ++)
            {

                Queue::subscribe($endpoint, $this->testingTopic, $channel, function (Message $msg) {

                    $this->testingCounts ++;

                    try
                    {
                        $msg->done();

                        $this->comResultCount(['result' => 'ok']);
                    }
                    catch (\Exception $e)
                    {
                        $this->comResultCount(['result' => 'exception', 'msg' => $e->getMessage()]);
                    }

                }, $this->targetSeconds);

            }
        }

        private function checkTargetIsReached($realTime)
        {
            $targetReached = false;

            if ($this->targetMode == self::MOD_IN_REQUESTS)
            {
                if ($this->testingCounts >= $this->targetRequests)
                {
                    $targetReached = true;
                }
            }
            else if ($this->targetMode == self::MOD_IN_SECONDS)
            {
                if ($realTime)
                {
                    $runSeconds = time() - $this->taskBeginTime;
                }
                else
                {
                    $runSeconds = $this->monitorRunSeconds;
                }

                if ($runSeconds >= $this->targetSeconds)
                {
                    $targetReached = true;
                }
            }
            else
            {
                $targetReached = false;
            }

            return $targetReached;
        }

        private function comResultCount($result)
        {
            if ($result['result'] == 'ok')
            {
                $this->successCounts ++;
            }
            else if ($result['result'] == 'error')
            {
                $this->failedCounts ++;

                $msg = $result['error'];

                if (isset($this->failedMsgTops[$msg]))
                {
                    $this->failedMsgTops[$msg] ++;
                }
                else
                {
                    $this->failedMsgTops[$msg] = 1;
                }
            }
            else if ($result['result'] == 'exception')
            {
                $this->exceptionCounts ++;

                $msg = $result['msg'];

                if (isset($this->exceptionMsgTops[$msg]))
                {
                    $this->exceptionMsgTops[$msg] ++;
                }
                else
                {
                    $this->exceptionMsgTops[$msg] = 1;
                }
            }
        }

        private function monitorReports()
        {
            $lastReportAndExit = false;

            echo "\n\n\n";
            echo '---Testing Reports---', "\n";

            echo 'JobConcurrency: ', $this->jobConcurrency;

            if ($this->targetMode == self::MOD_IN_REQUESTS)
            {
                echo ', TargetRequests: ', $this->targetRequests, "\n";
            }
            else if ($this->targetMode == self::MOD_IN_SECONDS)
            {
                echo ', TargetDuration: ', $this->targetSeconds, ' seconds', "\n";
            }
            echo "\n";

            echo 'Count[tests/success/fails/exceptions]: ', $this->testingCounts, '/', $this->successCounts, '/', $this->failedCounts, '/', $this->exceptionCounts, "\n";

            if ($this->failedMsgTops)
            {
                echo 'FTops: ';
                foreach ($this->failedMsgTops as $msg => $count)
                {
                    echo $msg, ' -> ', $count, ' | ';
                }
                echo "\n";
            }

            if ($this->exceptionMsgTops)
            {
                echo 'ETops: ';
                foreach ($this->exceptionMsgTops as $msg => $count)
                {
                    echo $msg, ' -> ', $count, ' | ';
                }
                echo "\n";
            }

            echo "\n";

            if ($this->monitorLastRun)
            {
                $timeElapsed = microtime(true) - $this->monitorLastRun;
                $countIncrements = ($this->successCounts + $this->failedCounts) - $this->monitorLastCounts;

                $speed = ceil($countIncrements / $timeElapsed);

                if ($speed > $this->monitorHighestSpeed)
                {
                    $this->monitorHighestSpeed = $speed;
                }

                echo 'Speed: ', $speed, '/s', ' (max ', $this->monitorHighestSpeed, ')', "\n";

                if ($speed == 0)
                {
                    if ($this->monitorLastNonSpeedTime == 0)
                    {
                        $this->monitorLastNonSpeedTime = time();
                    }
                    if (time() - $this->monitorLastNonSpeedTime > 15)
                    {
                        $lastReportAndExit = true;
                    }
                }
                else
                {
                    $this->monitorLastNonSpeedTime = 0;
                }
            }

            $this->monitorRunSeconds = time() - $this->taskBeginTime;

            echo 'Runs: ', $this->secondsToHR($this->monitorRunSeconds), "\n";

            $memoryUsedBytes = memory_get_usage(true);

            if ($memoryUsedBytes > $this->monitorHighestMemory)
            {
                $this->monitorHighestMemory = $memoryUsedBytes;
            }

            echo 'Memory: ', $this->bytesToHR($memoryUsedBytes), ' (max ', $this->bytesToHR($this->monitorHighestMemory), ')', "\n";

            $this->monitorLastRun = microtime(true);
            $this->monitorLastCounts = $this->successCounts + $this->failedCounts;

            if ($lastReportAndExit)
            {
                $this->halt('EXIT: Long time with zero speed');
            }
        }

        private function detectTargetMode($requests)
        {
            if (is_numeric($requests))
            {
                $this->targetMode = self::MOD_IN_REQUESTS;
                $this->targetRequests = $requests;
            }
            else
            {
                $this->targetMode = self::MOD_IN_SECONDS;
                $this->targetSeconds = intval(substr($requests, 0, -1));
            }
        }

        private function secondsToHR($seconds)
        {
            $dtF = new \DateTime('@0');
            $dtT = new \DateTime("@$seconds");
            return $dtF->diff($dtT)->format('%a days, %h hours, %i minutes and %s seconds');
        }

        private function bytesToHR($bytes, $decimals = 2) {
            $sz = 'BKMGTP';
            $factor = floor((strlen($bytes) - 1) / 3);
            return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
        }

        private function halt($msg)
        {
            Timer::stop($this->monitorTimerKey);

            echo $msg, "\n";

            swoole_event_exit();

            exit(0);
        }
    }
}