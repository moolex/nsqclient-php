<?php
/**
 * NSQ Connection Pool
 * User: moyo
 * Date: 4/11/16
 * Time: 4:44 PM
 */

namespace NSQClient\Async\Tests\Connection;

use NSQClient\Async\Connection\Pool;

class PoolTest extends \PHPUnit_Framework_TestCase
{
    private $node = ['host' => '127.0.0.1', 'port' => ['tcp' => 4150]];

    private $flags = ['topic', 'channel'];

    public function test_hosting_read()
    {
        $classN1 = new class { public function name() { return 'n1'; } };
        $classN2 = new class { public function name() { return 'n2'; } };

        $nodeID_1 = null;
        $nodeID_2 = null;

        $result_1 = Pool::hosting(Pool::MOD_R, $this->node, $this->flags, function ($slotID) use (&$nodeID_1, $classN1) {
            // first initialize
            $nodeID_1 = $slotID;
            return new $classN1;
        });

        // test if got right node
        $this->assertEquals('n1', $result_1->name());

        $result_2 = Pool::hosting(Pool::MOD_R, $this->node, $this->flags, function ($slotID) use (&$nodeID_2, $classN2) {
            // never initialize
            $nodeID_2 = $slotID;
            return new $classN2;
        });

        // test if got new node (force locking for subscribe)
        $this->assertEquals('n2', $result_2->name());

        // test slot id is valid
        $this->assertTrue(is_numeric($nodeID_1));
        $this->assertTrue(is_numeric($nodeID_2));
    }

    public function test_hosting_write()
    {
        $classN1 = new class { public function name() { return 'n1'; } };
        $classN2 = new class { public function name() { return 'n2'; } };
        $classN3 = new class { public function name() { return 'n3'; } };

        $nodeID_1 = null;
        $nodeID_2 = null;
        $nodeID_3 = null;

        $result_1 = Pool::hosting(Pool::MOD_W, $this->node, $this->flags, function ($slotID) use (&$nodeID_1, $classN1) {
            // first initialize
            $nodeID_1 = $slotID;
            return new $classN1;
        });

        // test if got right node
        $this->assertEquals('n1', $result_1->name());

        $result_2 = Pool::hosting(Pool::MOD_W, $this->node, $this->flags, function ($slotID) use (&$nodeID_2, $classN2) {
            // second initialize
            $nodeID_2 = $slotID;
            return new $classN2;
        });

        // test if got diff node
        $this->assertEquals('n2', $result_2->name());

        // test if pool is new
        $this->assertNotEquals($nodeID_1, $nodeID_2);

        // unlocking
        call_user_func_array(Pool::lockCallback(function ($r) {}), ['T_Result', $nodeID_2]);

        $result_3 = Pool::hosting(Pool::MOD_W, $this->node, $this->flags, function ($slotID) use (&$nodeID_3, $classN3) {
            // second initialize
            $nodeID_3 = $slotID;
            return new $classN3;
        });

        // test if got node_2
        $this->assertEquals('n2', $result_3->name());

        // test if pool is reused
        $this->assertEquals(null, $nodeID_3);
    }

    public function test_release()
    {
        $classN = new class { public function name() { return 'n'; } };

        $cliMax = 100;

        $slotIDs = [];

        $memory_start = memory_get_usage();

        for ($i = 0; $i < $cliMax; $i ++)
        {
            Pool::hosting(Pool::MOD_W, $this->node, $this->flags, function ($slotID) use (&$slotIDs, $classN) {
                $slotIDs[] = $slotID;
                return new $classN;
            });
        }

        $memory_finish = memory_get_usage();

        foreach ($slotIDs as $slotID)
        {
            Pool::release($slotID);
        }

        $memory_released = memory_get_usage();

        // tests

        $this->assertTrue($memory_finish > $memory_start);

        $this->assertTrue($memory_finish > $memory_released);
    }
}