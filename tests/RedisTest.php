<?php

namespace Pop\Cache\Test;

use Pop\Cache\Cache;
use Pop\Cache\Adapter\Redis;
use Pop\Cache\Clock\MutableClock;
use PHPUnit\Framework\TestCase;

class RedisCacheableStub
{
    public string $marker = 'should-not-reconstruct';
}

class RedisTest extends TestCase
{

    public function testConstructor()
    {
        $cache = new Redis();
        $this->assertInstanceOf('Pop\Cache\Adapter\Redis', $cache);
        $this->assertInstanceOf('Redis', $cache->redis());
        $this->assertNotEmpty($cache->getVersion());
    }

    public function testNamespaceIsolation()
    {
        $cache1 = new Redis(namespace: 'ns1');
        $cache2 = new Redis(namespace: 'ns2');

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
        $cache  = new Redis(namespace: 'hash-test');
        $method = new \ReflectionMethod($cache, 'key');
        $method->setAccessible(true);
        $key = $method->invoke($cache, 'some-id');

        $this->assertStringNotContainsString('some-id', $key);
        $this->assertStringContainsString(sha1('some-id'), $key);
    }

    public function testGetItemDefaultDisambiguatesMissFromCachedFalse()
    {
        $cache = new Redis();
        $cache->saveItem('default-test-flag', false);

        $this->assertFalse($cache->getItem('default-test-flag', 'MISS'));
        $this->assertEquals('MISS', $cache->getItem('default-test-nonexistent', 'MISS'));
        $this->assertFalse($cache->getItem('default-test-nonexistent-2'));
    }

    public function testGetItemTtlDefaultDisambiguatesMissFromNeverExpires()
    {
        $cache = new Redis();
        $cache->saveItem('default-test-ttl', 'value');

        $this->assertEquals(0, $cache->getItemTtl('default-test-ttl', -1));
        $this->assertEquals(-1, $cache->getItemTtl('default-test-nonexistent', -1));
        $this->assertEquals(0, $cache->getItemTtl('default-test-nonexistent-2'));
    }

    public function testCachedObjectsComeBackAsIncompleteClass()
    {
        $cache = new Redis();
        $cache->saveItem('object-test', new RedisCacheableStub());
        $result = $cache->getItem('object-test');
        $this->assertInstanceOf('__PHP_Incomplete_Class', $result);
    }

    public function testSaveAndLoad()
    {
        $cache = new Redis();
        $cache->saveItem('foo', 'bar', 300);
        $this->assertEquals('bar', $cache->getItem('foo'));
        $this->assertEquals(300, $cache->getItemTtl('foo'));
        $this->assertTrue($cache->hasItem('foo'));
    }

    public function testGetExpiredItem()
    {
        $clock = new MutableClock();
        $cache = new Redis(clock: $clock);
        $cache->saveItem('foo', 'bar', 1);
        $clock->advance(2);
        $this->assertFalse($cache->getItem('foo'));
        $cache->clear();
        $cache->destroy();
    }

    public function testRemove()
    {
        $cache = new Redis();
        $cache->saveItem('foo', 'bar');
        $this->assertEquals('bar', $cache->getItem('foo'));
        $cache->deleteItem('foo');
        $this->assertFalse($cache->getItem('foo'));
        $cache->clear();
        $cache->destroy();
    }

    public function testIncrementItemCreatesAtInitialThenApplies()
    {
        // Redis counters persist in the running service across separate PHP CLI process runs, unlike
        // some other backends. Every test below wraps its body in try/finally and calls $cache->clear()
        // in the finally block - unconditionally, even on assertion failure - so it bumps this namespace's
        // version and a subsequent run (of this test, or of this file) always starts from a fresh counter
        // instead of colliding with a leftover value. (Cleanup placed only after the assertions, with no
        // finally, would never run on a failing assertion - since PHPUnit aborts the test method on the
        // first failure - which would leave stale state to poison the very next run.)
        $cache = new Redis(namespace: 'incr-test');
        try {
            $result = $cache->incrementItem('counter', 5, 100);
            $this->assertEquals(105, $result);
        } finally {
            $cache->clear();
        }
    }

    public function testIncrementItemOnExistingKeyAddsAmount()
    {
        $cache = new Redis(namespace: 'incr-existing-test');
        try {
            $cache->incrementItem('counter', 1, 10);
            $result = $cache->incrementItem('counter', 5);
            $this->assertEquals(16, $result);
        } finally {
            $cache->clear();
        }
    }

    public function testDecrementItemAllowsNegativeResult()
    {
        $cache = new Redis(namespace: 'decr-test');
        try {
            $result = $cache->decrementItem('counter', 10, 5);
            $this->assertEquals(-5, $result);
        } finally {
            $cache->clear();
        }
    }

    public function testIncrementItemWithZeroAmountPeeksWithoutMutating()
    {
        $cache = new Redis(namespace: 'peek-test');
        try {
            $cache->incrementItem('counter', 1, 50);
            $peek1 = $cache->incrementItem('counter', 0);
            $peek2 = $cache->incrementItem('counter', 0);

            $this->assertEquals(51, $peek1);
            $this->assertEquals(51, $peek2);
        } finally {
            $cache->clear();
        }
    }

    public function testIncrementItemOnNonNumericValueThrows()
    {
        $cache  = new Redis(namespace: 'nonnum-test');
        $method = new \ReflectionMethod($cache, 'key');
        $method->setAccessible(true);
        $key = $method->invoke($cache, 'not-a-number');

        $cache->redis()->set($key, 'hello');

        try {
            $this->expectException(\Pop\Cache\Adapter\Exception::class);
            $cache->incrementItem('not-a-number');
        } finally {
            $cache->clear();
        }
    }

    public function testIncrementItemStoresRawScalarNotEnvelope()
    {
        $cache = new Redis(namespace: 'raw-test');
        try {
            $cache->incrementItem('counter', 1, 41);

            $method = new \ReflectionMethod($cache, 'key');
            $method->setAccessible(true);
            $key = $method->invoke($cache, 'counter');

            $this->assertSame('42', $cache->redis()->get($key));
        } finally {
            $cache->clear();
        }
    }

    public function testIncrementItemHonorsTtlOnCreate()
    {
        $cache = new Redis(namespace: 'ttl-test');
        try {
            $cache->incrementItem('counter', 1, 0, 60);

            $method = new \ReflectionMethod($cache, 'key');
            $method->setAccessible(true);
            $key = $method->invoke($cache, 'counter');

            $this->assertEquals(60, $cache->redis()->ttl($key));
        } finally {
            $cache->clear();
        }
    }

    public function testIncrementItemViaEvalScriptExercisesTheAtomicPathDirectly()
    {
        $cache = new Redis(namespace: 'eval-path-test');
        try {
            // A second, independent increment immediately after the first proves the create+increment
            // sequence and the plain-increment sequence both go through the same script correctly.
            $first  = $cache->incrementItem('counter', 3, 10, 30);
            $second = $cache->incrementItem('counter', 4);

            $this->assertEquals(13, $first);
            $this->assertEquals(17, $second);
        } finally {
            $cache->clear();
        }
    }

    public function testGetItemOnCounterKeyIsGracefulMissNotCrash()
    {
        $cache = new Redis(namespace: 'guard-test');
        try {
            $cache->incrementItem('counter', 1, 41);

            $this->assertFalse($cache->getItem('counter'));
            $this->assertEquals(0, $cache->getItemTtl('counter'));
            $this->assertEquals('fallback', $cache->getItem('counter', 'fallback'));
        } finally {
            $cache->clear();
        }
    }

    public function testNullValuedItemRoundTripsCorrectly()
    {
        $cache = new Redis(namespace: 'null-value-test');
        try {
            $cache->saveItem('nullkey', null);

            $this->assertTrue($cache->hasItem('nullkey'));
            $this->assertNull($cache->getItem('nullkey', 'MISS'));
        } finally {
            $cache->clear();
        }
    }

    public function testTaggingViaCacheFacade()
    {
        $adapter = new Redis(namespace: 'tag-test');
        $cache   = new Cache($adapter);

        $cache->saveTaggedItem('item1', 'v1', ['tagA']);
        $this->assertEquals('v1', $cache->getItem('item1'));

        $cache->invalidateTag('tagA');
        $this->assertFalse($cache->getItem('item1'));
    }

    public function testRememberStampedeProtectionViaCacheFacade()
    {
        $adapter = new Redis(namespace: 'remember-stampede-test');
        $cache   = new Cache($adapter);

        try {
            $calls = 0;

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
        } finally {
            $adapter->clear();
        }
    }

}