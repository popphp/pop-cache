<?php

namespace {
    ob_start();
}

namespace Pop\Cache\Test {

    use Pop\Cache\Cache;
    use Pop\Cache\Adapter\Session;
    use Pop\Cache\Clock\MutableClock;
    use PHPUnit\Framework\TestCase;

    class SessionCacheableStub
    {
        public string $marker = 'should-not-reconstruct';
    }

    class SessionTest extends TestCase
    {
        public function testConstructor()
        {
            $cache = new Session();
            $this->assertInstanceOf('Pop\Cache\Adapter\Session', $cache);
        }

        public function testNamespaceIsolation()
        {
            $cache1 = new Session(namespace: 'ns1');
            $cache2 = new Session(namespace: 'ns2');

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
            $cache  = new Session(60, 'hash-test');
            $method = new \ReflectionMethod($cache, 'key');
            $key = $method->invoke($cache, 'some-id');

            $this->assertStringNotContainsString('some-id', $key);
            $this->assertStringContainsString(sha1('some-id'), $key);
        }

        public function testGetItemDefaultDisambiguatesMissFromCachedFalse()
        {
            $cache = new Session();
            $cache->saveItem('default-test-flag', false);

            $this->assertFalse($cache->getItem('default-test-flag', 'MISS'));
            $this->assertEquals('MISS', $cache->getItem('default-test-nonexistent', 'MISS'));
            $this->assertFalse($cache->getItem('default-test-nonexistent-2'));
        }

        public function testGetItemTtlDefaultDisambiguatesMissFromNeverExpires()
        {
            $cache = new Session();
            $cache->saveItem('default-test-ttl', 'value');

            $this->assertEquals(0, $cache->getItemTtl('default-test-ttl', -1));
            $this->assertEquals(-1, $cache->getItemTtl('default-test-nonexistent', -1));
            $this->assertEquals(0, $cache->getItemTtl('default-test-nonexistent-2'));
        }

        public function testClearRemovesEntriesFromSession()
        {
            $cache = new Session(60, 'leak-test');
            $cache->saveItem('foo', 'bar');
            $key = 'leak-test:' . sha1('foo');
            $this->assertArrayHasKey($key, $_SESSION['_POP_CACHE_']);
            $cache->clear();
            $this->assertArrayNotHasKey($key, $_SESSION['_POP_CACHE_']);
        }

        public function testClearDoesNotMatchANamespaceThatIsAPrefixOfAnotherNamespace()
        {
            $cache1  = new Session(0, 'ns1');
            $cache10 = new Session(0, 'ns10');

            $cache1->saveItem('foo', 'from-ns1');
            $cache10->saveItem('foo', 'from-ns10');

            $cache1->clear();

            $this->assertFalse($cache1->getItem('foo'));
            $this->assertEquals('from-ns10', $cache10->getItem('foo'));
        }

        public function testCachedObjectsComeBackAsIncompleteClass()
        {
            $cache = new Session();
            $cache->saveItem('object-test', new SessionCacheableStub());
            $result = $cache->getItem('object-test');
            $this->assertInstanceOf('__PHP_Incomplete_Class', $result);
        }

        public function testSaveAndLoad()
        {
            $cache = new Session();
            $cache->saveItem('foo', 'bar', 300);
            $this->assertEquals('bar', $cache->getItem('foo'));
            $this->assertEquals(300, $cache->getItemTtl('foo'));
            $this->assertTrue($cache->hasItem('foo'));
        }

        public function testGetExpiredItem()
        {
            $clock = new MutableClock();
            $cache = new Session(clock: $clock);
            $cache->saveItem('foo', 'bar', 1);
            $clock->advance(2);
            $this->assertFalse($cache->getItem('foo'));
            $cache->clear();
            $cache->destroy();
        }

        public function testRemove()
        {
            $cache = new Session();
            $cache->saveItem('foo', 'bar');
            $this->assertEquals('bar', $cache->getItem('foo'));
            $cache->deleteItem('foo');
            $this->assertFalse($cache->getItem('foo'));
            $cache->clear();
            $cache->destroy();
        }

        public function testIncrementItemCreatesAtInitialThenApplies()
        {
            $cache  = new Session(namespace: 'incr-test');
            $result = $cache->incrementItem('counter', 5, 100);

            $this->assertEquals(105, $result);
            $this->assertEquals(105, $cache->getItem('counter'));
        }

        public function testDecrementItemAllowsNegativeResult()
        {
            $cache  = new Session(namespace: 'decr-test');
            $result = $cache->decrementItem('counter', 10, 5);

            $this->assertEquals(-5, $result);
        }

        public function testIncrementItemOnNonNumericValueThrows()
        {
            $cache = new Session(namespace: 'nonnum-test');
            $cache->saveItem('not-a-number', 'hello');

            $this->expectException(\Pop\Cache\Adapter\Exception::class);
            $cache->incrementItem('not-a-number');
        }

        public function testDecrementItemOnNonNumericValueThrows()
        {
            $cache = new Session(namespace: 'nonnum-decr-test');
            $cache->saveItem('not-a-number', 'hello');

            $this->expectException(\Pop\Cache\Adapter\Exception::class);
            $cache->decrementItem('not-a-number');
        }

        public function testIncrementItemWithZeroAmountPeeksWithoutMutating()
        {
            $cache = new Session(namespace: 'peek-test');
            $cache->incrementItem('counter', 1, 50);
            $peek = $cache->incrementItem('counter', 0);

            $this->assertEquals(51, $peek);
        }

        public function testTaggingViaCacheFacade()
        {
            $adapter = new Session(namespace: 'tag-test');
            $cache   = new Cache($adapter);

            $cache->saveTaggedItem('item1', 'v1', ['tagA']);
            $this->assertEquals('v1', $cache->getItem('item1'));

            $cache->invalidateTag('tagA');
            $this->assertFalse($cache->getItem('item1'));
        }

        public function testRememberStampedeProtectionViaCacheFacade()
        {
            $adapter = new Session(namespace: 'remember-stampede-test');
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

}

