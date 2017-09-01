<?php
/**
 * NSQ Buffer
 * User: moyo
 * Date: 8/29/16
 * Time: 5:45 PM
 */

namespace NSQClient\Async\Tests\Foundation;

use NSQClient\Async\Foundation\Buffer;

class BufferTest extends \PHPUnit_Framework_TestCase
{
    public function test_io()
    {
        $sample = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $buffer = new Buffer($sample);

        $this->assertEquals(true, $buffer->readable());

        $_ = $buffer->read(13);

        $this->assertEquals(false, $buffer->eof());

        $_ = $buffer->read(13);

        $this->assertEquals(false, $buffer->readable());
        $this->assertEquals(true, $buffer->eof());

        unset($buffer);
    }
}