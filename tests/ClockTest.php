<?php

namespace Pop\Cache\Test;

use Pop\Cache\Clock\SystemClock;
use Pop\Cache\Clock\MutableClock;
use PHPUnit\Framework\TestCase;

class ClockTest extends TestCase
{

    public function testSystemClockReturnsCurrentTime()
    {
        $clock  = new SystemClock();
        $before = time();
        $now    = $clock->now();
        $after  = time();

        $this->assertGreaterThanOrEqual($before, $now);
        $this->assertLessThanOrEqual($after, $now);
    }

    public function testMutableClockDefaultsToCurrentTime()
    {
        $before = time();
        $clock  = new MutableClock();
        $after  = time();

        $this->assertGreaterThanOrEqual($before, $clock->now());
        $this->assertLessThanOrEqual($after, $clock->now());
    }

    public function testMutableClockCanBeConstructedWithAnExplicitTime()
    {
        $clock = new MutableClock(1000);
        $this->assertEquals(1000, $clock->now());
    }

    public function testMutableClockSetTime()
    {
        $clock = new MutableClock(1000);
        $clock->setTime(2000);
        $this->assertEquals(2000, $clock->now());
    }

    public function testMutableClockAdvance()
    {
        $clock = new MutableClock(1000);
        $clock->advance(50);
        $this->assertEquals(1050, $clock->now());
    }

}
