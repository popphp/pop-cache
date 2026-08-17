<?php

namespace Pop\Cache\Test;

use Pop\Cache\Cache;
use Pop\Cache\Adapter;
use Pop\Cache\Clock\MutableClock;
use Pop\Cache\Clock\SystemClock;
use Pop\Cache\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase
{

    public function testConstructor()
    {
        mkdir(__DIR__ . '/cache');
        chmod(__DIR__ . '/cache', 0777);
        $cache = new Cache(new Adapter\File(__DIR__ . '/cache', 60));
        $this->assertEquals(60, $cache->getTtl());
        $this->assertInstanceOf('Pop\Cache\Cache', $cache);
        $this->assertInstanceOf('Pop\Cache\Adapter\File', $cache->adapter());
        $this->assertTrue($cache->getAvailableAdapters()['file']);
        $this->assertTrue($cache->isAvailable('file'));
    }

    public function testGetAvailableAdaptersIncludesMemoryAndNull()
    {
        $cache = new Cache(new Adapter\Memory());
        $this->assertTrue($cache->getAvailableAdapters()['memory']);
        $this->assertTrue($cache->getAvailableAdapters()['null']);
        $this->assertTrue($cache->isAvailable('memory'));
        $this->assertTrue($cache->isAvailable('null'));
    }

    public function testValidateKeyThrowsForReservedCharacters()
    {
        $cache  = new Cache(new Adapter\Memory());
        $method = new \ReflectionMethod($cache, 'validateKey');

        $this->expectException(InvalidArgumentException::class);
        $method->invoke($cache, 'has{brace');
    }

    public function testValidateKeyThrowsForEmptyString()
    {
        $cache  = new Cache(new Adapter\Memory());
        $method = new \ReflectionMethod($cache, 'validateKey');

        $this->expectException(InvalidArgumentException::class);
        $method->invoke($cache, '');
    }

    public function testValidateKeyAllowsNormalKeys()
    {
        $cache  = new Cache(new Adapter\Memory());
        $method = new \ReflectionMethod($cache, 'validateKey');

        $method->invoke($cache, 'a-perfectly-normal-key');
        $this->assertTrue(true);
    }

    public static function reservedCharacterProvider(): array
    {
        return [
            'open brace'    => ['has{brace'],
            'close brace'   => ['has}brace'],
            'open paren'    => ['has(paren'],
            'close paren'   => ['has)paren'],
            'forward slash' => ['has/slash'],
            'backslash'     => ['has\\backslash'],
            'at sign'       => ['has@at'],
            'colon'         => ['has:colon'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('reservedCharacterProvider')]
    public function testValidateKeyThrowsForEachReservedCharacter(string $key)
    {
        $cache  = new Cache(new Adapter\Memory());
        $method = new \ReflectionMethod($cache, 'validateKey');

        $this->expectException(InvalidArgumentException::class);
        $method->invoke($cache, $key);
    }

    public function testSaveItemThrowsForReservedCharacterKey()
    {
        $cache = new Cache(new Adapter\Memory());
        $this->expectException(InvalidArgumentException::class);
        $cache->saveItem('bad{key}', 'value');
    }

    public function testGetItemThrowsForReservedCharacterKey()
    {
        $cache = new Cache(new Adapter\Memory());
        $this->expectException(InvalidArgumentException::class);
        $cache->getItem('bad{key}');
    }

    public function testHasItemThrowsForReservedCharacterKey()
    {
        $cache = new Cache(new Adapter\Memory());
        $this->expectException(InvalidArgumentException::class);
        $cache->hasItem('bad{key}');
    }

    public function testDeleteItemThrowsForReservedCharacterKey()
    {
        $cache = new Cache(new Adapter\Memory());
        $this->expectException(InvalidArgumentException::class);
        $cache->deleteItem('bad{key}');
    }

    public function testGetReturnsDefaultWhenMissing()
    {
        $cache = new Cache(new Adapter\Memory());
        $this->assertNull($cache->get('missing-key'));
        $this->assertEquals('fallback', $cache->get('missing-key', 'fallback'));
    }

    public function testGetReturnsStoredValue()
    {
        $cache = new Cache(new Adapter\Memory());
        $cache->saveItem('psr-get-key', 'stored-value');
        $this->assertEquals('stored-value', $cache->get('psr-get-key'));
    }

    public function testDeleteReturnsTrue()
    {
        $cache = new Cache(new Adapter\Memory());
        $cache->saveItem('psr-delete-key', 'value');
        $this->assertTrue($cache->delete('psr-delete-key'));
        $this->assertFalse($cache->hasItem('psr-delete-key'));
    }

    public function testHasReflectsPresence()
    {
        $cache = new Cache(new Adapter\Memory());
        $this->assertFalse($cache->has('psr-has-key'));
        $cache->saveItem('psr-has-key', 'value');
        $this->assertTrue($cache->has('psr-has-key'));
    }

    public function testClearReturnsTrue()
    {
        $cache = new Cache(new Adapter\Memory());
        $cache->saveItem('psr-clear-key', 'value');
        $this->assertTrue($cache->clear());
        $this->assertFalse($cache->hasItem('psr-clear-key'));
    }

    public function testSetStoresValueWithIntTtl()
    {
        $cache = new Cache(new Adapter\Memory());
        $this->assertTrue($cache->set('psr-set-int', 'value', 300));
        $this->assertEquals('value', $cache->get('psr-set-int'));
        $this->assertEquals(300, $cache->getItemTtl('psr-set-int'));
    }

    public function testSetStoresValueWithNullTtlUsingAdapterDefault()
    {
        $cache = new Cache(new Adapter\Memory(60));
        $this->assertTrue($cache->set('psr-set-null', 'value'));
        $this->assertEquals(60, $cache->getItemTtl('psr-set-null'));
    }

    public function testSetStoresValueWithDateIntervalTtl()
    {
        $cache = new Cache(new Adapter\Memory());
        $this->assertTrue($cache->set('psr-set-interval', 'value', new \DateInterval('PT300S')));
        $this->assertEquals(300, $cache->getItemTtl('psr-set-interval'));
    }

    public function testSetWithZeroTtlDeletesInsteadOfCaching()
    {
        $cache = new Cache(new Adapter\Memory());
        $cache->saveItem('psr-set-zero', 'existing-value');
        $this->assertTrue($cache->set('psr-set-zero', 'new-value', 0));
        $this->assertFalse($cache->has('psr-set-zero'));
    }

    public function testSetWithNegativeTtlDeletesInsteadOfCaching()
    {
        $cache = new Cache(new Adapter\Memory());
        $cache->saveItem('psr-set-negative', 'existing-value');
        $this->assertTrue($cache->set('psr-set-negative', 'new-value', -10));
        $this->assertFalse($cache->has('psr-set-negative'));
    }

    public function testSaveItemStillTreatsZeroTtlAsNeverExpires()
    {
        $cache = new Cache(new Adapter\Memory());
        $cache->saveItem('psr-saveitem-zero', 'value', 0);
        $this->assertTrue($cache->has('psr-saveitem-zero'));
        $this->assertEquals(0, $cache->getItemTtl('psr-saveitem-zero'));
    }

    public function testCacheImplementsPsr16CacheInterface()
    {
        $cache = new Cache(new Adapter\Memory());
        $this->assertInstanceOf(\Psr\SimpleCache\CacheInterface::class, $cache);
    }

    public function testGetMultipleReturnsValuesKeyedByRequestedKeys()
    {
        $cache = new Cache(new Adapter\Memory());
        $cache->saveItem('multi-a', 'value-a');
        $cache->saveItem('multi-b', 'value-b');

        $result = $cache->getMultiple(['multi-a', 'multi-b', 'multi-missing'], 'MISS');

        $this->assertEquals([
            'multi-a'       => 'value-a',
            'multi-b'       => 'value-b',
            'multi-missing' => 'MISS'
        ], $result);
    }

    public function testGetMultipleValidatesAllKeysBeforeAnyRetrieval()
    {
        $cache = new Cache(new Adapter\Memory());
        $this->expectException(InvalidArgumentException::class);
        $cache->getMultiple(['valid-key', 'bad{key}']);
    }

    public function testSetMultipleStoresAllPairs()
    {
        $cache = new Cache(new Adapter\Memory());
        $this->assertTrue($cache->setMultiple(['multi-set-a' => 'value-a', 'multi-set-b' => 'value-b'], 300));

        $this->assertEquals('value-a', $cache->get('multi-set-a'));
        $this->assertEquals('value-b', $cache->get('multi-set-b'));
        $this->assertEquals(300, $cache->getItemTtl('multi-set-a'));
    }

    public function testSetMultipleValidatesAllKeysBeforeAnyWrite()
    {
        $cache = new Cache(new Adapter\Memory());

        try {
            $cache->setMultiple(['valid-key' => 'value', 'bad{key}' => 'value']);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertFalse($cache->has('valid-key'));
        }
    }

    public function testDeleteMultipleRemovesAllKeys()
    {
        $cache = new Cache(new Adapter\Memory());
        $cache->saveItem('multi-delete-a', 'value-a');
        $cache->saveItem('multi-delete-b', 'value-b');

        $this->assertTrue($cache->deleteMultiple(['multi-delete-a', 'multi-delete-b']));

        $this->assertFalse($cache->has('multi-delete-a'));
        $this->assertFalse($cache->has('multi-delete-b'));
    }

    public function testDeleteMultipleValidatesAllKeysBeforeAnyDeletion()
    {
        $cache = new Cache(new Adapter\Memory());
        $cache->saveItem('multi-delete-keep', 'value');

        try {
            $cache->deleteMultiple(['multi-delete-keep', 'bad{key}']);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertTrue($cache->has('multi-delete-keep'));
        }
    }

    public function testSaveAndLoad()
    {
        $cache = new Cache(new Adapter\File(__DIR__ . '/cache', 60));
        $cache->saveItem('foo', 'bar', 300);
        $this->assertEquals(300, $cache->getItemTtl('foo'));
        $this->assertEquals('bar', $cache->getItem('foo'));
        $this->assertEquals('bar', $cache->foo);
        $this->assertEquals('bar', $cache['foo']);
    }

    public function testGetItemDefaultPassesThroughToAdapter()
    {
        mkdir(__DIR__ . '/cache-default-test');
        $cache = new Cache(new Adapter\File(__DIR__ . '/cache-default-test', 60));
        $cache->saveItem('flag', false);

        $this->assertFalse($cache->getItem('flag', 'MISS'));
        $this->assertEquals('MISS', $cache->getItem('nonexistent', 'MISS'));

        $cache->clear();
        $cache->destroy();
    }

    public function testGetItemTtlDefaultPassesThroughToAdapter()
    {
        mkdir(__DIR__ . '/cache-ttl-default-test');
        $cache = new Cache(new Adapter\File(__DIR__ . '/cache-ttl-default-test', 0));
        $cache->saveItem('never-expires', 'value');

        $this->assertEquals(0, $cache->getItemTtl('never-expires', -1));
        $this->assertEquals(-1, $cache->getItemTtl('nonexistent', -1));

        $cache->clear();
        $cache->destroy();
    }

    public function testMagicMethods()
    {
        $cache = new Cache(new Adapter\File(__DIR__ . '/cache', 60));
        $cache->baz     = 789;
        $cache['test3'] = 999;
        $this->assertTrue(isset($cache->baz));
        $this->assertTrue(isset($cache['test3']));
        unset($cache->baz);
        unset($cache['test3']);
        $this->assertFalse(isset($cache->baz));
        $this->assertFalse(isset($cache['test3']));
    }

    public function testMagicSetThrowsForReservedCharacterKey()
    {
        $cache = new Cache(new Adapter\Memory());
        $this->expectException(InvalidArgumentException::class);
        $cache['bad{key}'] = 'value';
    }

    public function testSaveAndDeleteItems()
    {
        $cache = new Cache(new Adapter\File(__DIR__ . '/cache', 60));
        $cache->saveItems([
            'test1' => 123,
            'test2' => 456
        ]);
        $this->assertEquals(123, $cache->getItem('test1'));
        $this->assertEquals(456, $cache->getItem('test2'));
        $cache->deleteItems(['test1', 'test2']);
        $this->assertFalse($cache->getItem('test1'));
        $this->assertFalse($cache->getItem('test2'));
    }

    public function testSaveItemsThrowsForReservedCharacterKey()
    {
        $cache = new Cache(new Adapter\Memory());
        $this->expectException(InvalidArgumentException::class);
        $cache->saveItems(['good' => 1, 'bad{key}' => 2]);
    }

    public function testSaveItemsIsAllOrNothingWhenAKeyIsInvalid()
    {
        $cache = new Cache(new Adapter\Memory());
        try {
            $cache->saveItems(['good' => 1, 'bad{key}' => 2]);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $e) {
            $this->assertFalse($cache->hasItem('good'));
        }
    }

    public function testDeleteItemsThrowsForReservedCharacterKey()
    {
        $cache = new Cache(new Adapter\Memory());
        $this->expectException(InvalidArgumentException::class);
        $cache->deleteItems(['good', 'bad{key}']);
    }

    public function testDeleteItemsIsAllOrNothingWhenAKeyIsInvalid()
    {
        $cache = new Cache(new Adapter\Memory());
        $cache->saveItem('good', 1);

        try {
            $cache->deleteItems(['good', 'bad{key}']);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $e) {
            $this->assertTrue($cache->hasItem('good'));
        }
    }

    public function testRemove()
    {
        $cache = new Cache(new Adapter\File(__DIR__ . '/cache', 60));
        $cache->deleteItem('foo');
        $this->assertFalse($cache->getItem('foo'));
    }

    public function testClear()
    {
        $cache1 = new Cache(new Adapter\File(__DIR__ . '/cache', 60));
        $cache1->saveItem('foo', 'bar');
        $cache1->saveItem('baz', 123);
        $this->assertEquals('bar', $cache1->getItem('foo'));
        $this->assertEquals(123, $cache1->getItem('baz'));
        $this->assertTrue($cache1->hasItem('foo'));
        $cache1->clear();
        $cache1->destroy();
        $this->assertFalse($cache1->getItem('foo'));
        $this->assertFalse($cache1->getItem('baz'));
        if (file_exists(__DIR__ . '/cache')) {
            rmdir(__DIR__ . '/cache');
        }
    }

    public function testInvalidArgumentExceptionImplementsBothPsrMarkerInterfaces()
    {
        $exception = new InvalidArgumentException('test');
        $this->assertInstanceOf(\Psr\SimpleCache\InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(\Psr\Cache\InvalidArgumentException::class, $exception);
    }

    public function testRememberInvokesCallbackOnceOnAMiss()
    {
        $cache = new Cache(new Adapter\Memory());
        $calls = 0;

        $result = $cache->remember('remember-miss-key', function () use (&$calls) {
            $calls++;
            return 'computed-value';
        });

        $this->assertEquals('computed-value', $result);
        $this->assertEquals(1, $calls);
        $this->assertEquals('computed-value', $cache->getItem('remember-miss-key'));
    }

    public function testRememberDoesNotInvokeCallbackOnAHit()
    {
        $cache = new Cache(new Adapter\Memory());
        $cache->saveItem('remember-hit-key', 'already-cached');
        $calls = 0;

        $result = $cache->remember('remember-hit-key', function () use (&$calls) {
            $calls++;
            return 'should-not-be-used';
        });

        $this->assertEquals('already-cached', $result);
        $this->assertEquals(0, $calls);
    }

    public function testRememberCachesAFalseValueWithoutReinvokingCallback()
    {
        $cache = new Cache(new Adapter\Memory());
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;
            return false;
        };

        $first  = $cache->remember('remember-false-key', $callback);
        $second = $cache->remember('remember-false-key', $callback);

        $this->assertFalse($first);
        $this->assertFalse($second);
        $this->assertEquals(1, $calls);
    }

    public function testRememberCachesANullValueWithoutReinvokingCallback()
    {
        $cache = new Cache(new Adapter\Memory());
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;
            return null;
        };

        $cache->remember('remember-null-key', $callback);
        $cache->remember('remember-null-key', $callback);

        $this->assertEquals(1, $calls);
    }

    public function testRememberCachesAZeroValueWithoutReinvokingCallback()
    {
        $cache = new Cache(new Adapter\Memory());
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;
            return 0;
        };

        $cache->remember('remember-zero-key', $callback);
        $cache->remember('remember-zero-key', $callback);

        $this->assertEquals(1, $calls);
    }

    public function testRememberPassesTtlThroughToSaveItem()
    {
        $cache = new Cache(new Adapter\Memory());
        $cache->remember('remember-ttl-key', fn() => 'value', 300);

        $this->assertEquals(300, $cache->getItemTtl('remember-ttl-key'));
    }

    public function testRememberUsesAdapterGlobalTtlWhenTtlOmitted()
    {
        $cache = new Cache(new Adapter\Memory(60));
        $cache->remember('remember-default-ttl-key', fn() => 'value');

        $this->assertEquals(60, $cache->getItemTtl('remember-default-ttl-key'));
    }

    public function testRememberPropagatesCallbackExceptionAndCachesNothing()
    {
        $cache = new Cache(new Adapter\Memory());

        try {
            $cache->remember('remember-exception-key', function () {
                throw new \RuntimeException('computation failed');
            });
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertEquals('computation failed', $e->getMessage());
            $this->assertFalse($cache->hasItem('remember-exception-key'));
        }
    }

    public function testIncrementItemDelegatesToAdapter()
    {
        $cache  = new Cache(new Adapter\Memory());
        $result = $cache->incrementItem('counter', 5, 100);

        $this->assertEquals(105, $result);
    }

    public function testDecrementItemDelegatesToAdapter()
    {
        $cache  = new Cache(new Adapter\Memory());
        $result = $cache->decrementItem('counter', 10, 5);

        $this->assertEquals(-5, $result);
    }

    public function testIncrementItemValidatesKey()
    {
        $cache = new Cache(new Adapter\Memory());

        $this->expectException(InvalidArgumentException::class);
        $cache->incrementItem('reserved:key');
    }

    public function testDecrementItemValidatesKey()
    {
        $cache = new Cache(new Adapter\Memory());

        $this->expectException(InvalidArgumentException::class);
        $cache->decrementItem('reserved:key');
    }

    public function testIncrementItemDefaultsMatchAdapterInterface()
    {
        $cache = new Cache(new Adapter\Memory());

        $this->assertEquals(1, $cache->incrementItem('counter'));
        $this->assertEquals(2, $cache->incrementItem('counter'));
    }

    public function testSaveTaggedItemSavesTheItem()
    {
        $cache = new Cache(new Adapter\Memory());
        $cache->saveTaggedItem('product-1', 'widget', ['products', 'electronics']);

        $this->assertEquals('widget', $cache->getItem('product-1'));
    }

    public function testSaveTaggedItemAddsIdToEachTagsMemberIndex()
    {
        $adapter = new Adapter\Memory();
        $cache   = new Cache($adapter);

        $cache->saveTaggedItem('product-1', 'widget', ['products', 'electronics']);

        $this->assertContains('product-1', $adapter->getItem('@tag:' . sha1('products'), []));
        $this->assertContains('product-1', $adapter->getItem('@tag:' . sha1('electronics'), []));
    }

    public function testSaveTaggedItemStoresItsOwnTagListForLaterReconciliation()
    {
        $adapter = new Adapter\Memory();
        $cache   = new Cache($adapter);

        $cache->saveTaggedItem('product-1', 'widget', ['products', 'electronics']);

        $this->assertEquals(
            ['products', 'electronics'],
            $adapter->getItem('@tags-for:' . sha1('product-1'), [])
        );
    }

    public function testSaveTaggedItemReTaggingRemovesOldTagMembership()
    {
        $adapter = new Adapter\Memory();
        $cache   = new Cache($adapter);

        $cache->saveTaggedItem('item1', 'v1', ['tagA']);
        $cache->saveTaggedItem('item1', 'v2', ['tagB']);

        $this->assertNotContains('item1', $adapter->getItem('@tag:' . sha1('tagA'), []));
        $this->assertContains('item1', $adapter->getItem('@tag:' . sha1('tagB'), []));
        $this->assertEquals('v2', $cache->getItem('item1'));
    }

    public function testSaveTaggedItemReTaggingDeletesEmptiedTagIndex()
    {
        $adapter = new Adapter\Memory();
        $cache   = new Cache($adapter);

        $cache->saveTaggedItem('item1', 'v1', ['tagA']);
        $cache->saveTaggedItem('item1', 'v2', ['tagB']);

        $this->assertFalse($adapter->hasItem('@tag:' . sha1('tagA')));
    }

    public function testSaveTaggedItemReTaggingLeavesTagIndexIntactWhenOtherMembersRemain()
    {
        $adapter = new Adapter\Memory();
        $cache   = new Cache($adapter);

        $cache->saveTaggedItem('item1', 'v1', ['tagA']);
        $cache->saveTaggedItem('item2', 'v2', ['tagA']);

        // Re-tag item1 away from tagA; item2 is still tagged tagA, so tagA's index must survive
        // non-empty rather than being deleted.
        $cache->saveTaggedItem('item1', 'v1-updated', ['tagB']);

        $this->assertTrue($adapter->hasItem('@tag:' . sha1('tagA')));
        $this->assertEquals(['item2'], $adapter->getItem('@tag:' . sha1('tagA'), []));
    }

    public function testSaveTaggedItemDoesNotDuplicateExistingMembership()
    {
        $adapter = new Adapter\Memory();
        $cache   = new Cache($adapter);

        $cache->saveTaggedItem('item1', 'v1', ['tagA']);
        $cache->saveTaggedItem('item1', 'v2', ['tagA']);

        $this->assertEquals(['item1'], $adapter->getItem('@tag:' . sha1('tagA'), []));
    }

    public function testSaveTaggedItemWithEmptyTagsBehavesLikePlainSaveItem()
    {
        $cache = new Cache(new Adapter\Memory());
        $cache->saveTaggedItem('plain-key', 'value', []);

        $this->assertEquals('value', $cache->getItem('plain-key'));
    }

    public function testSaveTaggedItemWithEmptyTagsDeletesTagsForBookkeepingKey()
    {
        $adapter = new Adapter\Memory();
        $cache   = new Cache($adapter);

        $cache->saveTaggedItem('item1', 'v1', ['tagA']);
        $cache->saveTaggedItem('item1', 'v2', []);

        $this->assertFalse($adapter->hasItem('@tags-for:' . sha1('item1')));
        $this->assertNotContains('item1', $adapter->getItem('@tag:' . sha1('tagA'), []));
    }

    public function testSaveTaggedItemValidatesId()
    {
        $cache = new Cache(new Adapter\Memory());

        $this->expectException(InvalidArgumentException::class);
        $cache->saveTaggedItem('bad{id}', 'value', ['tag1']);
    }

    public function testSaveTaggedItemValidatesEachTagName()
    {
        $cache = new Cache(new Adapter\Memory());

        $this->expectException(InvalidArgumentException::class);
        $cache->saveTaggedItem('id1', 'value', ['good-tag', 'bad:tag']);
    }

    public function testSaveTaggedItemPassesTtlThroughToSaveItem()
    {
        $cache = new Cache(new Adapter\Memory());
        $cache->saveTaggedItem('ttl-key', 'value', ['tag1'], 300);

        $this->assertEquals(300, $cache->getItemTtl('ttl-key'));
    }

    public function testSaveTaggedItemTagIndexNeverExpiresRegardlessOfItemTtl()
    {
        $adapter = new Adapter\Memory();
        $cache   = new Cache($adapter);

        $cache->saveTaggedItem('ttl-key', 'value', ['tag1'], 300);

        $this->assertEquals(0, $adapter->getItemTtl('@tag:' . sha1('tag1')));
    }

    public function testInvalidateTagDeletesAllTaggedItems()
    {
        $cache = new Cache(new Adapter\Memory());
        $cache->saveTaggedItem('item1', 'v1', ['tagA']);
        $cache->saveTaggedItem('item2', 'v2', ['tagA']);

        $cache->invalidateTag('tagA');

        $this->assertFalse($cache->getItem('item1'));
        $this->assertFalse($cache->getItem('item2'));
    }

    public function testInvalidateTagLeavesOtherTaggedItemsAlone()
    {
        $cache = new Cache(new Adapter\Memory());
        $cache->saveTaggedItem('item1', 'v1', ['tagA']);
        $cache->saveTaggedItem('item2', 'v2', ['tagB']);

        $cache->invalidateTag('tagA');

        $this->assertFalse($cache->getItem('item1'));
        $this->assertEquals('v2', $cache->getItem('item2'));
    }

    public function testInvalidateTagOnMultiTaggedItemAlsoCleansItsOtherTagIndex()
    {
        $adapter = new Adapter\Memory();
        $cache   = new Cache($adapter);

        $cache->saveTaggedItem('item1', 'v1', ['tagA', 'tagB']);
        $cache->invalidateTag('tagA');

        $this->assertNotContains('item1', $adapter->getItem('@tag:' . sha1('tagB'), []));
    }

    public function testInvalidateTagOnMultiTaggedItemDeletesItFromBothTags()
    {
        $cache = new Cache(new Adapter\Memory());
        $cache->saveTaggedItem('item1', 'v1', ['tagA', 'tagB']);

        $cache->invalidateTag('tagA');

        $this->assertFalse($cache->getItem('item1'));
    }

    public function testInvalidateTagRemovesTagsForBookkeepingKeyOfEachMember()
    {
        $adapter = new Adapter\Memory();
        $cache   = new Cache($adapter);

        $cache->saveTaggedItem('item1', 'v1', ['tagA']);
        $cache->invalidateTag('tagA');

        $this->assertFalse($adapter->hasItem('@tags-for:' . sha1('item1')));
    }

    public function testInvalidateTagDeletesTheTagIndexItself()
    {
        $adapter = new Adapter\Memory();
        $cache   = new Cache($adapter);

        $cache->saveTaggedItem('item1', 'v1', ['tagA']);
        $cache->invalidateTag('tagA');

        $this->assertFalse($adapter->hasItem('@tag:' . sha1('tagA')));
    }

    public function testInvalidateTagOnATagWithNoMembersIsANoOp()
    {
        $cache = new Cache(new Adapter\Memory());
        $cache->invalidateTag('never-used-tag');
        $this->assertTrue(true);
    }

    public function testInvalidateTagValidatesTagName()
    {
        $cache = new Cache(new Adapter\Memory());

        $this->expectException(InvalidArgumentException::class);
        $cache->invalidateTag('bad:tag');
    }

    public function testInvalidateTagsInvalidatesEveryListedTag()
    {
        $cache = new Cache(new Adapter\Memory());
        $cache->saveTaggedItem('item1', 'v1', ['tagA']);
        $cache->saveTaggedItem('item2', 'v2', ['tagB']);

        $cache->invalidateTags(['tagA', 'tagB']);

        $this->assertFalse($cache->getItem('item1'));
        $this->assertFalse($cache->getItem('item2'));
    }

    public function testSaveTaggedItemReconciliationSurvivesTagsForKeyOutlivingItemTtl()
    {
        $clock   = new MutableClock();
        $adapter = new Adapter\Memory(0, $clock);
        $cache   = new Cache($adapter);

        $cache->saveTaggedItem('user-7', 'v1', ['users'], 300);
        $clock->advance(400);
        $cache->saveTaggedItem('user-7', 'v2', ['premium-users'], 300);

        $cache->invalidateTag('users');

        $this->assertEquals('v2', $cache->getItem('user-7'));
        $this->assertContains('user-7', $adapter->getItem('@tag:' . sha1('premium-users'), []));
    }

    public function testTagIndexAndTagsForBookkeepingBothNeverExpireRegardlessOfItemTtl()
    {
        $adapter = new Adapter\Memory();
        $cache   = new Cache($adapter);

        $cache->saveTaggedItem('item1', 'v1', ['tagA'], 300);

        $this->assertEquals(0, $adapter->getItemTtl('@tag:' . sha1('tagA')));
        $this->assertEquals(0, $adapter->getItemTtl('@tags-for:' . sha1('item1')));
    }

    public function testSaveTaggedItemRejectsNonStringTag()
    {
        $cache = new Cache(new Adapter\Memory());

        $this->expectException(InvalidArgumentException::class);
        $cache->saveTaggedItem('item1', 'value', [123]);
    }

    public function testInvalidateTagsValidatesAllTagsBeforeInvalidatingAny()
    {
        $cache = new Cache(new Adapter\Memory());
        $cache->saveTaggedItem('item1', 'v1', ['ok1']);
        $cache->saveTaggedItem('item2', 'v2', ['ok2']);

        try {
            $cache->invalidateTags(['ok1', 'bad:tag', 'ok2']);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            // Neither ok1 nor ok2 should have been invalidated, since validation happens up front.
            $this->assertEquals('v1', $cache->getItem('item1'));
            $this->assertEquals('v2', $cache->getItem('item2'));
        }
    }

    public function testInvalidateTagsRejectsNonStringTag()
    {
        $cache = new Cache(new Adapter\Memory());

        $this->expectException(InvalidArgumentException::class);
        $cache->invalidateTags(['good-tag', 123]);
    }

    public function testCacheDefaultsToSystemClockWhenNoneProvided()
    {
        $cache    = new Cache(new Adapter\Memory());
        $property = new \ReflectionProperty($cache, 'clock');

        $this->assertInstanceOf(SystemClock::class, $property->getValue($cache));
    }

    public function testCacheAcceptsInjectedClock()
    {
        $clock    = new MutableClock(12345);
        $cache    = new Cache(new Adapter\Memory(), $clock);
        $property = new \ReflectionProperty($cache, 'clock');

        $this->assertSame($clock, $property->getValue($cache));
    }

    public function testRememberWithBetaZeroWritesNoMetaKey()
    {
        $adapter = new Adapter\Memory();
        $cache   = new Cache($adapter);

        $cache->remember('no-meta-key', fn() => 'value', 300);

        $this->assertFalse($adapter->hasItem('@remember:' . sha1('no-meta-key')));
    }

    public function testRememberWithBetaOmittedStillBehavesExactlyAsBefore()
    {
        $cache = new Cache(new Adapter\Memory());
        $calls = 0;

        $first  = $cache->remember('omitted-beta-key', function () use (&$calls) {
            $calls++;
            return 'computed-value';
        });
        $second = $cache->remember('omitted-beta-key', function () use (&$calls) {
            $calls++;
            return 'should-not-be-used';
        });

        $this->assertEquals('computed-value', $first);
        $this->assertEquals('computed-value', $second);
        $this->assertEquals(1, $calls);
    }

    public function testRememberRecordsMetaKeyWithStartTtlAndDelta()
    {
        $clock   = new MutableClock(1000);
        $adapter = new Adapter\Memory(0, $clock);
        $cache   = new Cache($adapter, $clock);

        $cache->remember('meta-key', function () use ($clock) {
            $clock->advance(5);
            return 'value';
        }, 300, 1.0);

        $meta = $adapter->getItem('@remember:' . sha1('meta-key'), null);

        $this->assertNotNull($meta);
        $this->assertEquals(1000, $meta['start']);
        $this->assertEquals(300, $meta['ttl']);
        $this->assertEquals(5, $meta['delta']);
    }

    public function testRememberMetaRecordsResolvedTtlWhenTtlOmitted()
    {
        $adapter = new Adapter\Memory(60);
        $cache   = new Cache($adapter);

        $cache->remember('resolved-ttl-key', fn() => 'value', null, 1.0);

        $meta = $adapter->getItem('@remember:' . sha1('resolved-ttl-key'), null);
        $this->assertEquals(60, $meta['ttl']);
    }

    public function testRememberWithMissingMetaKeyDegradesToNormalHit()
    {
        $cache = new Cache(new Adapter\Memory());
        $cache->saveItem('some-key', 'cached-value');
        $calls = 0;

        $result = $cache->remember('some-key', function () use (&$calls) {
            $calls++;
            return 'should-not-be-used';
        }, null, 1.0);

        $this->assertEquals('cached-value', $result);
        $this->assertEquals(0, $calls);
    }

    public function testRememberDoesNotTriggerEarlyRecomputeShortlyAfterCreation()
    {
        $clock   = new MutableClock(0);
        $adapter = new Adapter\Memory(0, $clock);
        $cache   = new Cache($adapter, $clock);
        $calls   = 0;

        $cache->remember('fresh-key', function () use (&$calls, $clock) {
            $calls++;
            $clock->advance(1); // delta = 1
            return 'v1';
        }, 1000000, 1.0);

        $clock->setTime(2); // shortly after creation (start=0), nowhere near expiry (ttl=1000000)

        $result = $cache->remember('fresh-key', function () use (&$calls) {
            $calls++;
            return 'v2';
        }, 1000000, 1.0);

        $this->assertEquals('v1', $result);
        $this->assertEquals(1, $calls);
    }

    public function testRememberTriggersEarlyRecomputeNearExpiryWithHighBeta()
    {
        $clock   = new MutableClock(0);
        $adapter = new Adapter\Memory(0, $clock);
        $cache   = new Cache($adapter, $clock);
        $calls   = 0;

        $cache->remember('near-expiry-key', function () use (&$calls, $clock) {
            $calls++;
            $clock->advance(1); // delta = 1
            return 'v1';
        }, 1000, 100000000.0);

        $clock->setTime(999); // 1 second before real expiry (start=0, ttl=1000) -- NOT yet actually expired

        $result = $cache->remember('near-expiry-key', function () use (&$calls) {
            $calls++;
            return 'v2';
        }, 1000, 100000000.0);

        $this->assertEquals('v2', $result);
        $this->assertEquals(2, $calls);
    }

    public function testRememberPropagatesCallbackExceptionAndCachesNothingWithBetaEnabled()
    {
        $cache = new Cache(new Adapter\Memory());

        try {
            $cache->remember('exception-with-beta-key', function () {
                throw new \RuntimeException('computation failed');
            }, null, 1.0);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertEquals('computation failed', $e->getMessage());
            $this->assertFalse($cache->hasItem('exception-with-beta-key'));
        }
    }

    public function testRememberWithBetaEnabledDoesNotRecomputeNeverExpiringItem()
    {
        $cache = new Cache(new Adapter\Memory());
        $calls = 0;

        for ($i = 0; $i < 5; $i++) {
            $cache->remember('never-expires-key', function () use (&$calls) {
                $calls++;
                return 'value';
            }, 0, 1.0);
        }

        $this->assertEquals(1, $calls);
    }

    public function testRememberWithBetaEnabledWritesNoMetaKeyForNeverExpiringItem()
    {
        $adapter = new Adapter\Memory();
        $cache   = new Cache($adapter);

        $cache->remember('never-expires-meta-key', fn() => 'value', 0, 1.0);

        $this->assertFalse($adapter->hasItem('@remember:' . sha1('never-expires-meta-key')));
    }

    public function testRememberWithBetaEnabledDoesNotRecomputeWhenTtlOmittedResolvesToNeverExpires()
    {
        $cache = new Cache(new Adapter\Memory());
        $calls = 0;

        $cache->remember('resolved-never-expires-key', function () use (&$calls) {
            $calls++;
            return 'value';
        }, null, 1.0);
        $cache->remember('resolved-never-expires-key', function () use (&$calls) {
            $calls++;
            return 'value2';
        }, null, 1.0);

        $this->assertEquals(1, $calls);
    }

    public function testRememberWithFlooredDeltaCanStillTriggerEarlyRecomputeForFastCallbacks()
    {
        $clock   = new MutableClock(0);
        $adapter = new Adapter\Memory(0, $clock);
        $cache   = new Cache($adapter, $clock);
        $calls   = 0;

        // Fast callback: the clock never advances inside it, so the real elapsed time is 0 seconds,
        // which must be floored to a minimum delta of 1 for early recomputation to be possible at all.
        $cache->remember('fast-callback-key', function () use (&$calls) {
            $calls++;
            return 'v1';
        }, 300, 1000000.0);

        $clock->setTime(299); // 1 second before real expiry (start=0, ttl=300) -- NOT yet actually expired

        $result = $cache->remember('fast-callback-key', function () use (&$calls) {
            $calls++;
            return 'v2';
        }, 300, 1000000.0);

        $this->assertEquals('v2', $result);
        $this->assertEquals(2, $calls);
    }
}