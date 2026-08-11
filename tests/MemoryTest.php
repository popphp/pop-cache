<?php

namespace Pop\Cache\Test;

use Pop\Cache\Adapter\Memory;
use Pop\Cache\Clock\MutableClock;
use PHPUnit\Framework\TestCase;

class MemoryCacheableStub
{
    public string $marker = 'should-not-reconstruct';
}

class MemoryTest extends TestCase
{

    public function testConstructor()
    {
        $cache = new Memory(60);
        $this->assertInstanceOf('Pop\Cache\Adapter\Memory', $cache);
        $this->assertEquals(60, $cache->getTtl());
    }

    public function testSaveAndLoad()
    {
        $cache = new Memory();
        $cache->saveItem('foo', 'bar', 300);
        $this->assertEquals('bar', $cache->getItem('foo'));
        $this->assertEquals(300, $cache->getItemTtl('foo'));
        $this->assertTrue($cache->hasItem('foo'));
    }

    public function testCachedObjectsComeBackAsTheSameLiveInstance()
    {
        $cache  = new Memory();
        $object = new MemoryCacheableStub();
        $cache->saveItem('object-test', $object);
        $result = $cache->getItem('object-test');
        $this->assertSame($object, $result);
    }

    public function testGetExpiredItem()
    {
        $clock = new MutableClock(1000);
        $cache = new Memory(0, $clock);
        $cache->saveItem('foo', 'bar', 10);

        $this->assertEquals('bar', $cache->getItem('foo'));

        $clock->advance(11);

        $this->assertFalse($cache->hasItem('foo'));
        $this->assertFalse($cache->getItem('foo'));
        $cache->clear();
        $cache->destroy();
    }

    public function testGetItemDefaultDisambiguatesMissFromCachedFalse()
    {
        $cache = new Memory();
        $cache->saveItem('default-test-flag', false);

        $this->assertFalse($cache->getItem('default-test-flag', 'MISS'));
        $this->assertEquals('MISS', $cache->getItem('default-test-nonexistent', 'MISS'));
        $this->assertFalse($cache->getItem('default-test-nonexistent-2'));
    }

    public function testGetItemTtlDefaultDisambiguatesMissFromNeverExpires()
    {
        $cache = new Memory();
        $cache->saveItem('default-test-ttl', 'value');

        $this->assertEquals(0, $cache->getItemTtl('default-test-ttl', -1));
        $this->assertEquals(-1, $cache->getItemTtl('default-test-nonexistent', -1));
        $this->assertEquals(0, $cache->getItemTtl('default-test-nonexistent-2'));
    }

    public function testDeleteItem()
    {
        $cache = new Memory();
        $cache->saveItem('foo', 'bar');
        $this->assertTrue($cache->hasItem('foo'));
        $cache->deleteItem('foo');
        $this->assertFalse($cache->hasItem('foo'));
    }

    public function testClear()
    {
        $cache = new Memory();
        $cache->saveItem('foo', 'bar');
        $cache->saveItem('baz', 'qux');
        $cache->clear();
        $this->assertFalse($cache->hasItem('foo'));
        $this->assertFalse($cache->hasItem('baz'));
    }

    public function testDestroy()
    {
        $cache = new Memory();
        $cache->saveItem('foo', 'bar');
        $cache->destroy();
        $this->assertFalse($cache->hasItem('foo'));
    }

    public function testIncrementItemCreatesAtInitialThenApplies()
    {
        $cache  = new Memory();
        $result = $cache->incrementItem('counter', 5, 100);

        $this->assertEquals(105, $result);
        $this->assertEquals(105, $cache->getItem('counter'));
    }

    public function testIncrementItemOnExistingKeyAddsAmount()
    {
        $cache = new Memory();
        $cache->incrementItem('counter', 1, 10);
        $result = $cache->incrementItem('counter', 5);

        $this->assertEquals(16, $result);
    }

    public function testIncrementItemDefaultAmountIsOne()
    {
        $cache = new Memory();
        $cache->incrementItem('counter');
        $result = $cache->incrementItem('counter');

        $this->assertEquals(2, $result);
    }

    public function testIncrementItemWithZeroAmountPeeksWithoutMutating()
    {
        $cache = new Memory();
        $cache->incrementItem('counter', 1, 50);
        $peek1 = $cache->incrementItem('counter', 0);
        $peek2 = $cache->incrementItem('counter', 0);

        $this->assertEquals(51, $peek1);
        $this->assertEquals(51, $peek2);
    }

    public function testDecrementItemCreatesAtInitialThenApplies()
    {
        $cache  = new Memory();
        $result = $cache->decrementItem('counter', 5, 100);

        $this->assertEquals(95, $result);
    }

    public function testDecrementItemAllowsNegativeResult()
    {
        $cache  = new Memory();
        $result = $cache->decrementItem('counter', 10, 5);

        $this->assertEquals(-5, $result);
    }

    public function testIncrementItemOnNonNumericValueThrows()
    {
        $cache = new Memory();
        $cache->saveItem('not-a-number', 'hello');

        $this->expectException(\Pop\Cache\Adapter\Exception::class);
        $cache->incrementItem('not-a-number');
    }

    public function testDecrementItemOnNonNumericValueThrows()
    {
        $cache = new Memory();
        $cache->saveItem('not-a-number', 'hello');

        $this->expectException(\Pop\Cache\Adapter\Exception::class);
        $cache->decrementItem('not-a-number');
    }

    public function testIncrementItemPassesTtlThroughToSaveItem()
    {
        $cache = new Memory();
        $cache->incrementItem('counter', 1, 0, 300);

        $this->assertEquals(300, $cache->getItemTtl('counter'));
    }

    public function testIncrementItemUsesGlobalTtlWhenTtlOmitted()
    {
        $cache = new Memory(60);
        $cache->incrementItem('counter');

        $this->assertEquals(60, $cache->getItemTtl('counter'));
    }

}
