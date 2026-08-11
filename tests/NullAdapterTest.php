<?php

namespace Pop\Cache\Test;

use Pop\Cache\Cache;
use Pop\Cache\Adapter\NullAdapter;
use PHPUnit\Framework\TestCase;

class NullAdapterTest extends TestCase
{

    public function testConstructor()
    {
        $cache = new NullAdapter(60);
        $this->assertInstanceOf('Pop\Cache\Adapter\NullAdapter', $cache);
        $this->assertEquals(60, $cache->getTtl());
    }

    public function testSaveItemIsANoOp()
    {
        $cache  = new NullAdapter();
        $result = $cache->saveItem('foo', 'bar');
        $this->assertSame($cache, $result);
        $this->assertFalse($cache->hasItem('foo'));
    }

    public function testGetItemAlwaysReturnsDefault()
    {
        $cache = new NullAdapter();
        $cache->saveItem('foo', 'bar');

        $this->assertFalse($cache->getItem('foo'));
        $this->assertEquals('MISS', $cache->getItem('foo', 'MISS'));
    }

    public function testHasItemAlwaysReturnsFalse()
    {
        $cache = new NullAdapter();
        $cache->saveItem('foo', 'bar');
        $this->assertFalse($cache->hasItem('foo'));
    }

    public function testGetItemTtlAlwaysReturnsDefault()
    {
        $cache = new NullAdapter();
        $cache->saveItem('foo', 'bar', 300);

        $this->assertEquals(0, $cache->getItemTtl('foo'));
        $this->assertEquals(-1, $cache->getItemTtl('foo', -1));
    }

    public function testDeleteItemClearAndDestroyAreNoOpsAndChainable()
    {
        $cache = new NullAdapter();

        $this->assertSame($cache, $cache->deleteItem('foo'));
        $this->assertSame($cache, $cache->clear());
        $this->assertSame($cache, $cache->destroy());
    }

    public function testIncrementItemReturnsInitialPlusAmountEveryCall()
    {
        $cache = new NullAdapter();

        $this->assertEquals(105, $cache->incrementItem('counter', 5, 100));
        $this->assertEquals(105, $cache->incrementItem('counter', 5, 100));
    }

    public function testIncrementItemDefaultsToOnePlusZero()
    {
        $cache = new NullAdapter();
        $this->assertEquals(1, $cache->incrementItem('counter'));
    }

    public function testDecrementItemReturnsInitialMinusAmountEveryCall()
    {
        $cache = new NullAdapter();

        $this->assertEquals(95, $cache->decrementItem('counter', 5, 100));
        $this->assertEquals(95, $cache->decrementItem('counter', 5, 100));
    }

    public function testDecrementItemAllowsNegativeResult()
    {
        $cache = new NullAdapter();
        $this->assertEquals(-5, $cache->decrementItem('counter', 10, 5));
    }

    public function testTaggingIsANoOpChain()
    {
        $cache = new Cache(new NullAdapter());

        $cache->saveTaggedItem('item1', 'v1', ['tagA']);
        $this->assertFalse($cache->getItem('item1'));

        $cache->invalidateTag('tagA');
        $this->assertTrue(true);
    }

    public function testRememberStampedeProtectionIsANoOpChain()
    {
        $cache = new Cache(new NullAdapter());
        $calls = 0;

        $result = $cache->remember('stampede-key', function () use (&$calls) {
            $calls++;
            return 'computed-value';
        }, 300, 1.0);

        $this->assertEquals('computed-value', $result);
        $this->assertEquals(1, $calls);
    }

}
