<?php

namespace Pop\Cache\Test;

use Pop\Cache\Cache;
use Pop\Cache\Adapter\Apc;
use Pop\Cache\Clock\MutableClock;
use PHPUnit\Framework\TestCase;

class ApcTest extends TestCase
{

    public function testConstructor()
    {
        $cache = new Apc(60);
        $this->assertInstanceOf('Pop\Cache\Adapter\Apc', $cache);
        $this->assertTrue(isset($cache->getInfo()['ttl']));
    }

    public function testNamespaceIsolation()
    {
        $cache1 = new Apc(60, 'ns1');
        $cache2 = new Apc(60, 'ns2');

        $cache1->saveItem('shared-key', 'from-ns1');
        $cache2->saveItem('shared-key', 'from-ns2');

        $this->assertEquals('from-ns1', $cache1->getItem('shared-key'));
        $this->assertEquals('from-ns2', $cache2->getItem('shared-key'));

        $cache1->clear();

        $this->assertFalse($cache1->getItem('shared-key'));
        $this->assertEquals('from-ns2', $cache2->getItem('shared-key'));
    }

    public function testKeyIsHashed()
    {
        $cache  = new Apc(60, 'hash-test');
        $method = new \ReflectionMethod($cache, 'key');
        $method->setAccessible(true);
        $key = $method->invoke($cache, 'some-id');

        $this->assertStringNotContainsString('some-id', $key);
        $this->assertStringContainsString(sha1('some-id'), $key);
    }

    public function testGetItemDefaultDisambiguatesMissFromCachedFalse()
    {
        $cache = new Apc(60);
        $cache->saveItem('default-test-flag', false);

        $this->assertFalse($cache->getItem('default-test-flag', 'MISS'));
        $this->assertEquals('MISS', $cache->getItem('default-test-nonexistent', 'MISS'));
        $this->assertFalse($cache->getItem('default-test-nonexistent-2'));
    }

    public function testGetItemTtlDefaultDisambiguatesMissFromNeverExpires()
    {
        $cache = new Apc(0);
        $cache->saveItem('default-test-ttl', 'value');

        $this->assertEquals(0, $cache->getItemTtl('default-test-ttl', -1));
        $this->assertEquals(-1, $cache->getItemTtl('default-test-nonexistent', -1));
        $this->assertEquals(0, $cache->getItemTtl('default-test-nonexistent-2'));
    }

    public function testSaveAndLoad()
    {
        $cache = new Apc(60);
        $cache->saveItem('foo', 'bar', 300);
        $this->assertEquals('bar', $cache->getItem('foo'));
        $this->assertEquals(300, $cache->getItemTtl('foo'));
        $this->assertTrue($cache->hasItem('foo'));
    }

    public function testGetExpiredItem()
    {
        $clock = new MutableClock();
        $cache = new Apc(60, clock: $clock);
        $cache->saveItem('foo', 'bar', 1);
        $clock->advance(2);
        $this->assertFalse($cache->getItem('foo'));
        $cache->clear();
        $cache->destroy();
    }

    public function testRemove()
    {
        $cache = new Apc(60);
        $cache->saveItem('foo', 'bar');
        $this->assertEquals('bar', $cache->getItem('foo'));
        $cache->deleteItem('foo');
        $this->assertFalse($cache->getItem('foo'));
        $cache->clear();
        $cache->destroy();
    }

    public function testIncrementItemCreatesAtInitialThenApplies()
    {
        $cache  = new Apc(60, 'incr-test');
        $result = $cache->incrementItem('counter', 5, 100);

        $this->assertEquals(105, $result);
    }

    public function testIncrementItemOnExistingKeyAddsAmount()
    {
        $cache = new Apc(60, 'incr-existing-test');
        $cache->incrementItem('counter', 1, 10);
        $result = $cache->incrementItem('counter', 5);

        $this->assertEquals(16, $result);
    }

    public function testDecrementItemAllowsNegativeResult()
    {
        $cache  = new Apc(60, 'decr-test');
        $result = $cache->decrementItem('counter', 10, 5);

        $this->assertEquals(-5, $result);
    }

    public function testIncrementItemWithZeroAmountPeeksWithoutMutating()
    {
        $cache = new Apc(60, 'peek-test');
        $cache->incrementItem('counter', 1, 50);
        $peek1 = $cache->incrementItem('counter', 0);
        $peek2 = $cache->incrementItem('counter', 0);

        $this->assertEquals(51, $peek1);
        $this->assertEquals(51, $peek2);
    }

    public function testIncrementItemOnNonNumericValueThrows()
    {
        $cache  = new Apc(60, 'nonnum-test');
        $method = new \ReflectionMethod($cache, 'key');
        $method->setAccessible(true);
        $key = $method->invoke($cache, 'not-a-number');

        apcu_store($key, 'hello');

        $this->expectException(\Pop\Cache\Adapter\Exception::class);
        $cache->incrementItem('not-a-number');
    }

    public function testDecrementItemOnNonNumericValueThrows()
    {
        $cache  = new Apc(60, 'nonnum-decr-test');
        $method = new \ReflectionMethod($cache, 'key');
        $method->setAccessible(true);
        $key = $method->invoke($cache, 'not-a-number');

        apcu_store($key, 'hello');

        $this->expectException(\Pop\Cache\Adapter\Exception::class);
        $cache->decrementItem('not-a-number');
    }

    public function testIncrementItemHonorsTtlOnCreate()
    {
        $cache  = new Apc(0, 'ttl-test');
        $cache->incrementItem('counter', 1, 0, 60);

        $method = new \ReflectionMethod($cache, 'key');
        $method->setAccessible(true);
        $key = $method->invoke($cache, 'counter');

        $info = apcu_key_info($key);
        $this->assertEquals(60, $info['ttl']);
    }

    public function testIncrementItemStoresRawScalarNotEnvelope()
    {
        $cache = new Apc(60, 'raw-test');
        $cache->incrementItem('counter', 1, 41);

        $method = new \ReflectionMethod($cache, 'key');
        $method->setAccessible(true);
        $key = $method->invoke($cache, 'counter');

        $this->assertSame(42, apcu_fetch($key));
    }

    public function testGetItemOnCounterKeyIsGracefulMissNotCrash()
    {
        $cache = new Apc(60, 'guard-test');
        $cache->incrementItem('counter', 1, 41);

        $this->assertFalse($cache->getItem('counter'));
        $this->assertEquals(0, $cache->getItemTtl('counter'));
        $this->assertEquals('fallback', $cache->getItem('counter', 'fallback'));
    }

    public function testNullValuedItemRoundTripsCorrectly()
    {
        $cache = new Apc(60, 'null-value-test');
        $cache->saveItem('nullkey', null);

        $this->assertTrue($cache->hasItem('nullkey'));
        $this->assertNull($cache->getItem('nullkey', 'MISS'));
    }

    public function testTaggingViaCacheFacade()
    {
        $adapter = new Apc(60, 'tag-test');
        $cache   = new Cache($adapter);

        $cache->saveTaggedItem('item1', 'v1', ['tagA']);
        $this->assertEquals('v1', $cache->getItem('item1'));

        $cache->invalidateTag('tagA');
        $this->assertFalse($cache->getItem('item1'));
    }

    public function testRememberStampedeProtectionViaCacheFacade()
    {
        $adapter = new Apc(60, 'remember-stampede-test');
        $cache   = new Cache($adapter);
        $calls   = 0;

        $first  = $cache->remember('stampede-key', function () use (&$calls) {
            $calls++;
            return 'computed-value';
        }, 300, 1.0);
        $second = $cache->remember('stampede-key', function () use (&$calls) {
            $calls++;
            return 'should-not-be-used';
        }, 300, 1.0);

        $this->assertEquals('computed-value', $first);
        $this->assertEquals('computed-value', $second);
        $this->assertEquals(1, $calls);
    }

}