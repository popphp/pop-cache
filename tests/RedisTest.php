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

    public function testVersionIsResolvedOncePerInstanceNotOncePerOperation()
    {
        // Before memoization, NamespacedVersionedKeys::key() re-read the namespace version from the
        // backend on EVERY key build, so each of these four calls cost two round trips instead of
        // one -- doubling the traffic of the whole component. Counted here by watching the version
        // key's own access count via a spy on the raw client is not possible through the public API,
        // so this asserts the observable consequence instead: the memo is populated after first use
        // and never re-read.
        $cache = new Redis(namespace: 'version-memo-test');

        try {
            $property = new \ReflectionProperty($cache, 'resolvedVersion');

            $this->assertNull($property->getValue($cache), 'version must be lazy, not resolved in the constructor');

            $cache->saveItem('foo', 'bar');
            $first = $property->getValue($cache);
            $this->assertNotNull($first);

            $cache->getItem('foo');
            $cache->hasItem('foo');
            $cache->deleteItem('foo');

            $this->assertSame($first, $property->getValue($cache), 'the version must not be re-resolved');
        } finally {
            $cache->clear();
        }
    }

    public function testClearStillInvalidatesOnTheSameInstanceDespiteMemoization()
    {
        // The memo must not outlive the clear() that invalidates it, or clear() becomes a no-op for
        // the very instance that called it.
        $cache = new Redis(namespace: 'memo-clear-test');

        try {
            $cache->saveItem('foo', 'bar');
            $this->assertEquals('bar', $cache->getItem('foo'));

            $cache->clear();

            $this->assertFalse($cache->getItem('foo'));
        } finally {
            $cache->clear();
        }
    }

    public function testClearAdvancesTheMemoizedVersionRatherThanRereadingIt()
    {
        $cache = new Redis(namespace: 'memo-bump-test');

        try {
            $property = new \ReflectionProperty($cache, 'resolvedVersion');

            $cache->saveItem('foo', 'bar');
            $before = $property->getValue($cache);

            $cache->clear();

            $this->assertSame($before + 1, $property->getValue($cache));
        } finally {
            $cache->clear();
        }
    }

    public function testForgetVersionCausesTheNextKeyBuildToRereadTheBackend()
    {
        // The documented escape hatch for an instance that must observe a clear() performed
        // elsewhere.
        $a = new Redis(namespace: 'forget-test');
        $b = new Redis(namespace: 'forget-test');

        try {
            $a->saveItem('foo', 'bar');
            $this->assertEquals('bar', $b->getItem('foo'));

            $a->clear();

            // $b memoized the pre-clear version, so it still sees the old generation...
            $this->assertEquals('bar', $b->getItem('foo'));

            // ...until it is told to re-read.
            $method = new \ReflectionMethod($b, 'forgetVersion');
            $method->invoke($b);

            $this->assertFalse($b->getItem('foo'));
        } finally {
            $a->clear();
        }
    }

    public function testConnectTimeoutIsHonoredForAnUnreachableHost()
    {
        // 203.0.113.0/24 is TEST-NET-3 (RFC 5737): guaranteed unrouteable, so connect() has nothing
        // to do but time out. Without a timeout this sits for the OS-level TCP timeout, which is how
        // an untimed cache dependency turns into a blocked worker.
        $start = microtime(true);

        try {
            new Redis(host: '203.0.113.1', options: ['timeout' => 0.5]);
            $this->fail('Expected a connection failure.');
        } catch (\Pop\Cache\Adapter\Exception $exception) {
            $elapsed = microtime(true) - $start;
            $this->assertLessThan(3.0, $elapsed, 'connect() did not honour the timeout');
            // phpredis raises \RedisException here; the adapter normalises it so callers can catch
            // one exception type for every connection-time failure. The original is kept as $previous.
            $this->assertInstanceOf(\RedisException::class, $exception->getPrevious());
        }
    }

    public function testReadTimeoutIsAppliedToTheConnection()
    {
        $cache = new Redis(namespace: 'read-timeout-test', options: ['read_timeout' => 2.5]);

        try {
            $this->assertEqualsWithDelta(2.5, $cache->redis()->getOption(\Redis::OPT_READ_TIMEOUT), 0.01);
        } finally {
            $cache->clear();
        }
    }

    public function testPersistentConnectionStillWorksAndReportsPersistent()
    {
        $cache = new Redis(namespace: 'persistent-test', options: ['persistent' => true]);

        try {
            $cache->saveItem('foo', 'bar');
            $this->assertEquals('bar', $cache->getItem('foo'));
        } finally {
            $cache->clear();
        }
    }

    public function testTwoPersistentAdaptersOnDifferentDatabasesDoNotShareASocket()
    {
        // pconnect() pools by persistent_id. If the id ignored the database index, these two would
        // land on the same socket and the second SELECT would silently move the first one's
        // connection out from under it.
        $a = new Redis(namespace: 'pconnect-db-test', options: ['persistent' => true, 'database' => 0]);
        $b = new Redis(namespace: 'pconnect-db-test', options: ['persistent' => true, 'database' => 1]);

        try {
            $a->saveItem('foo', 'from-db-0');
            $b->saveItem('foo', 'from-db-1');

            $this->assertEquals('from-db-0', $a->getItem('foo'));
            $this->assertEquals('from-db-1', $b->getItem('foo'));
        } finally {
            $a->clear();
            $b->clear();
        }
    }

    public function testDatabaseSelectIsolatesEntries()
    {
        $db0 = new Redis(namespace: 'db-select-test', options: ['database' => 0]);
        $db1 = new Redis(namespace: 'db-select-test', options: ['database' => 1]);

        try {
            $db0->saveItem('only-in-zero', 'yes');

            $this->assertEquals('yes', $db0->getItem('only-in-zero'));
            $this->assertFalse($db1->getItem('only-in-zero'));
        } finally {
            $db0->clear();
            $db1->clear();
        }
    }

    public function testBadPasswordAgainstAnUnauthenticatedServerThrows()
    {
        // A server with no requirepass rejects AUTH outright, so this proves the auth() result is
        // actually checked rather than ignored.
        $this->expectException(\Pop\Cache\Adapter\Exception::class);

        new Redis(namespace: 'auth-test', options: ['password' => 'definitely-not-the-password']);
    }

    public function testUnrecognizedConnectionOptionThrowsRatherThanBeingSilentlyIgnored()
    {
        // The one thing an options array loses versus named parameters is a fatal on a typo. Without
        // this guard 'read_timout' means no read timeout at all, and nothing says so until a worker
        // hangs in production -- so the guard is what makes the array form safe to prefer.
        try {
            new Redis(namespace: 'bad-option-test', options: ['read_timout' => 1.0]);
            $this->fail('Expected an unrecognized-option failure.');
        } catch (\Pop\Cache\Adapter\Exception $exception) {
            $this->assertStringContainsString('read_timout', $exception->getMessage());
            $this->assertStringContainsString('read_timeout', $exception->getMessage());
        }
    }

    public function testOmittedConnectionOptionsReproduceThePreviousDefaults()
    {
        // Every default is the behaviour this adapter had before any of these options existed, so an
        // existing call that passes no options must be completely unaffected.
        $cache = new Redis(namespace: 'defaults-test');

        try {
            $this->assertEquals(0.0, $cache->redis()->getOption(\Redis::OPT_READ_TIMEOUT));

            $cache->saveItem('foo', 'bar');
            $this->assertEquals('bar', $cache->getItem('foo'));
        } finally {
            $cache->clear();
        }
    }

    public function testGetCounterReadsBackWhatIncrementItemWrote()
    {
        // The gap this closes: counters are raw scalars, getItem() expects an envelope, and key() is
        // protected -- so before getCounter() there was no public way to read a counter at all.
        $cache = new Redis(namespace: 'getcounter-test');

        try {
            $cache->incrementItem('counter', 1, 41);

            $this->assertSame(42, $cache->getCounter('counter'));
        } finally {
            $cache->clear();
        }
    }

    public function testGetCounterReturnsNullForACounterThatWasNeverTouched()
    {
        // The distinction incrementItem($id, 0) cannot make: it would create the key at $initial and
        // report 0, which is indistinguishable from a counter genuinely sitting at 0.
        $cache = new Redis(namespace: 'getcounter-missing-test');

        try {
            $this->assertNull($cache->getCounter('never-counted'));
        } finally {
            $cache->clear();
        }
    }

    public function testGetCounterDistinguishesAZeroCounterFromAnAbsentOne()
    {
        $cache = new Redis(namespace: 'getcounter-zero-test');

        try {
            $cache->incrementItem('at-zero', 0, 0);

            $this->assertSame(0, $cache->getCounter('at-zero'));
            $this->assertNull($cache->getCounter('absent'));
        } finally {
            $cache->clear();
        }
    }

    public function testGetCounterReturnsNullForANonNumericValueRatherThanCoercingToZero()
    {
        // incrementItem() throws on this same data. Silently reading it as 0 is how a rate limiter
        // ends up permanently believing no requests have happened.
        $cache  = new Redis(namespace: 'getcounter-nonnum-test');
        $method = new \ReflectionMethod($cache, 'key');

        try {
            $cache->redis()->set($method->invoke($cache, 'junk'), 'hello');

            $this->assertNull($cache->getCounter('junk'));
        } finally {
            $cache->clear();
        }
    }

    public function testGetCounterSeesDecrementItemToo()
    {
        $cache = new Redis(namespace: 'getcounter-decr-test');

        try {
            $cache->incrementItem('counter', 0, 10);
            $cache->decrementItem('counter', 4);

            $this->assertSame(6, $cache->getCounter('counter'));
        } finally {
            $cache->clear();
        }
    }

    public function testUsingADestroyedAdapterRaisesAClearExceptionNotATypeError()
    {
        // destroy() nulls the client while redis() is typed non-nullable, so this used to surface as
        // "Return value must be of type Redis, null returned" -- a TypeError that says nothing about
        // what the caller actually did wrong.
        $cache = new Redis(namespace: 'destroyed-test');
        $cache->destroy();

        $this->expectException(\Pop\Cache\Adapter\Exception::class);
        $cache->redis();
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