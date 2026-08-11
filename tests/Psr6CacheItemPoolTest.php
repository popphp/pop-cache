<?php

namespace Pop\Cache\Test;

use Pop\Cache\Adapter\File;
use Pop\Cache\Adapter\Memory;
use Pop\Cache\InvalidArgumentException;
use Pop\Cache\Psr6\CacheItem;
use Pop\Cache\Psr6\CacheItemPool;
use PHPUnit\Framework\TestCase;

class Psr6CacheItemPoolTest extends TestCase
{

    public function testConstructor()
    {
        $pool = new CacheItemPool(new Memory());
        $this->assertInstanceOf(CacheItemPool::class, $pool);
    }

    public function testGetItemReturnsAMissWhenNotPresent()
    {
        $pool = new CacheItemPool(new Memory());
        $item = $pool->getItem('missing-key');

        $this->assertInstanceOf(CacheItem::class, $item);
        $this->assertEquals('missing-key', $item->getKey());
        $this->assertFalse($item->isHit());
        $this->assertNull($item->get());
    }

    public function testGetItemReturnsAHitWhenPresent()
    {
        $adapter = new Memory();
        $adapter->saveItem('stored-key', 'stored-value');

        $pool = new CacheItemPool($adapter);
        $item = $pool->getItem('stored-key');

        $this->assertTrue($item->isHit());
        $this->assertEquals('stored-value', $item->get());
    }

    public function testGetItemDistinguishesAMissFromALegitimatelyCachedNullValue()
    {
        $adapter = new Memory();
        $adapter->saveItem('null-value-key', null);

        $pool = new CacheItemPool($adapter);
        $item = $pool->getItem('null-value-key');

        $this->assertTrue($item->isHit());
        $this->assertNull($item->get());
    }

    public function testGetItemThrowsForReservedCharacterKey()
    {
        $pool = new CacheItemPool(new Memory());
        $this->expectException(InvalidArgumentException::class);
        $pool->getItem('bad{key}');
    }

    public function testGetItemsWithEmptyArrayReturnsEmptyIterable()
    {
        $pool   = new CacheItemPool(new Memory());
        $result = $pool->getItems();
        $this->assertEmpty($result);
    }

    public function testGetItemsReturnsItemsKeyedByRequestedKeys()
    {
        $adapter = new Memory();
        $adapter->saveItem('multi-a', 'value-a');

        $pool  = new CacheItemPool($adapter);
        $items = $pool->getItems(['multi-a', 'multi-missing']);

        $this->assertTrue($items['multi-a']->isHit());
        $this->assertEquals('value-a', $items['multi-a']->get());
        $this->assertFalse($items['multi-missing']->isHit());
    }

    public function testGetItemsValidatesAllKeysBeforeAnyRetrieval()
    {
        $adapter = new Memory();
        $adapter->saveItem('valid-key-first', 'should-not-be-touched');

        $pool = new CacheItemPool($adapter);

        $this->expectException(InvalidArgumentException::class);
        $pool->getItems(['bad{key}', 'valid-key-first']);
    }

    public function testHasItemReflectsPresence()
    {
        $adapter = new Memory();
        $pool    = new CacheItemPool($adapter);

        $this->assertFalse($pool->hasItem('has-key'));

        $adapter->saveItem('has-key', 'value');
        $this->assertTrue($pool->hasItem('has-key'));
    }

    public function testHasItemThrowsForReservedCharacterKey()
    {
        $pool = new CacheItemPool(new Memory());
        $this->expectException(InvalidArgumentException::class);
        $pool->hasItem('bad{key}');
    }

    public function testDeleteItemRemovesTheItemAndReturnsTrue()
    {
        $adapter = new Memory();
        $adapter->saveItem('delete-key', 'value');

        $pool = new CacheItemPool($adapter);
        $this->assertTrue($pool->deleteItem('delete-key'));
        $this->assertFalse($pool->hasItem('delete-key'));
    }

    public function testDeleteItemThrowsForReservedCharacterKey()
    {
        $pool = new CacheItemPool(new Memory());
        $this->expectException(InvalidArgumentException::class);
        $pool->deleteItem('bad{key}');
    }

    public function testDeleteItemsRemovesAllKeysAndReturnsTrue()
    {
        $adapter = new Memory();
        $adapter->saveItem('delete-multi-a', 'value-a');
        $adapter->saveItem('delete-multi-b', 'value-b');

        $pool = new CacheItemPool($adapter);
        $this->assertTrue($pool->deleteItems(['delete-multi-a', 'delete-multi-b']));
        $this->assertFalse($pool->hasItem('delete-multi-a'));
        $this->assertFalse($pool->hasItem('delete-multi-b'));
    }

    public function testDeleteItemsValidatesAllKeysBeforeAnyDeletion()
    {
        $adapter = new Memory();
        $adapter->saveItem('delete-multi-keep', 'value');

        $pool = new CacheItemPool($adapter);

        try {
            $pool->deleteItems(['delete-multi-keep', 'bad{key}']);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertTrue($pool->hasItem('delete-multi-keep'));
        }
    }

    public function testClearRemovesAllItemsAndReturnsTrue()
    {
        $adapter = new Memory();
        $adapter->saveItem('clear-key', 'value');

        $pool = new CacheItemPool($adapter);
        $this->assertTrue($pool->clear());
        $this->assertFalse($pool->hasItem('clear-key'));
    }

    public function testSavePersistsTheItemWithAPositiveTtl()
    {
        $pool = new CacheItemPool($adapter = new Memory());
        $item = $pool->getItem('save-key');
        $item->set('save-value');
        $item->expiresAfter(300);

        $this->assertTrue($pool->save($item));
        $this->assertEquals('save-value', $adapter->getItem('save-key'));
        $this->assertEquals(300, $adapter->getItemTtl('save-key'));
    }

    public function testSavePersistsWithNullExpirationUsingAdapterDefault()
    {
        $pool = new CacheItemPool($adapter = new Memory(60));
        $item = $pool->getItem('save-null-key');
        $item->set('save-value');

        $this->assertTrue($pool->save($item));
        $this->assertEquals(60, $adapter->getItemTtl('save-null-key'));
    }

    public function testSaveWithDateIntervalExpirationPersistsCorrectTtl()
    {
        $pool = new CacheItemPool($adapter = new Memory());
        $item = $pool->getItem('save-interval-key');
        $item->set('save-value');
        $item->expiresAfter(new \DateInterval('PT300S'));

        $this->assertTrue($pool->save($item));
        $this->assertEquals(300, $adapter->getItemTtl('save-interval-key'));
    }

    public function testSaveWithZeroResolvedExpirationDeletesInsteadOfCaching()
    {
        $adapter = new Memory();
        $adapter->saveItem('save-zero-key', 'existing-value');

        $pool = new CacheItemPool($adapter);
        $item = $pool->getItem('save-zero-key');
        $item->set('new-value');
        $item->expiresAfter(0);

        $this->assertTrue($pool->save($item));
        $this->assertFalse($pool->hasItem('save-zero-key'));
    }

    public function testSaveWithNegativeResolvedExpirationDeletesInsteadOfCaching()
    {
        $adapter = new Memory();
        $adapter->saveItem('save-negative-key', 'existing-value');

        $pool = new CacheItemPool($adapter);
        $item = $pool->getItem('save-negative-key');
        $item->set('new-value');
        $item->expiresAfter(-10);

        $this->assertTrue($pool->save($item));
        $this->assertFalse($pool->hasItem('save-negative-key'));
    }

    public function testSaveRejectsAForeignCacheItemInterfaceImplementation()
    {
        $pool = new CacheItemPool(new Memory());

        $foreignItem = new class implements \Psr\Cache\CacheItemInterface {
            public function getKey(): string { return 'foreign-key'; }
            public function get(): mixed { return 'value'; }
            public function isHit(): bool { return false; }
            public function set(mixed $value): static { return $this; }
            public function expiresAt(?\DateTimeInterface $expiration): static { return $this; }
            public function expiresAfter(int|\DateInterval|null $time): static { return $this; }
        };

        $this->expectException(InvalidArgumentException::class);
        $pool->save($foreignItem);
    }

    public function testCacheItemPoolImplementsPsr6CacheItemPoolInterface()
    {
        $pool = new CacheItemPool(new Memory());
        $this->assertInstanceOf(\Psr\Cache\CacheItemPoolInterface::class, $pool);
    }

    public function testSaveDeferredDoesNotPersistUntilCommit()
    {
        $pool = new CacheItemPool($adapter = new Memory());
        $item = $pool->getItem('deferred-key');
        $item->set('deferred-value');

        $this->assertTrue($pool->saveDeferred($item));
        $this->assertFalse($adapter->hasItem('deferred-key'));
    }

    public function testCommitPersistsAllDeferredItemsAndReturnsTrue()
    {
        $pool  = new CacheItemPool($adapter = new Memory());
        $itemA = $pool->getItem('deferred-a');
        $itemA->set('value-a');
        $itemB = $pool->getItem('deferred-b');
        $itemB->set('value-b');

        $pool->saveDeferred($itemA);
        $pool->saveDeferred($itemB);

        $this->assertTrue($pool->commit());
        $this->assertEquals('value-a', $adapter->getItem('deferred-a'));
        $this->assertEquals('value-b', $adapter->getItem('deferred-b'));
    }

    public function testCommitClearsTheDeferredBufferSoASecondCommitIsANoOp()
    {
        $pool = new CacheItemPool($adapter = new Memory());
        $item = $pool->getItem('deferred-once');
        $item->set('value');
        $pool->saveDeferred($item);

        $this->assertTrue($pool->commit());
        $adapter->deleteItem('deferred-once');

        $this->assertTrue($pool->commit());
        $this->assertFalse($pool->hasItem('deferred-once'));
    }

    public function testDeleteItemAfterSaveDeferredPreventsResurrectionOnCommit()
    {
        $pool = new CacheItemPool($adapter = new Memory());
        $item = $pool->getItem('deferred-then-deleted');
        $item->set('value');

        $pool->saveDeferred($item);
        $pool->deleteItem('deferred-then-deleted');

        $this->assertTrue($pool->commit());
        $this->assertFalse($pool->hasItem('deferred-then-deleted'));
    }

    public function testSaveDeferredThrowsForReservedCharacterKey()
    {
        $pool = new CacheItemPool(new Memory());
        $item = new CacheItem('bad{key}');

        $this->expectException(InvalidArgumentException::class);
        $pool->saveDeferred($item);
    }

    public function testGetItemReturnsDeferredItemBeforeCommit()
    {
        $pool = new CacheItemPool($adapter = new Memory());
        $item = $pool->getItem('deferred-visible-key');
        $item->set('deferred-visible-value');
        $pool->saveDeferred($item);

        $this->assertFalse($adapter->hasItem('deferred-visible-key'));

        $sameItem = $pool->getItem('deferred-visible-key');
        $this->assertEquals('deferred-visible-value', $sameItem->get());
    }

    public function testHasItemReturnsTrueForDeferredItemBeforeCommit()
    {
        $pool = new CacheItemPool($adapter = new Memory());
        $item = $pool->getItem('deferred-has-key');
        $item->set('value');
        $pool->saveDeferred($item);

        $this->assertFalse($adapter->hasItem('deferred-has-key'));
        $this->assertTrue($pool->hasItem('deferred-has-key'));
    }

    public function testSaveRemovesMatchingDeferredEntrySoLaterSaveWins()
    {
        $pool = new CacheItemPool($adapter = new Memory());

        $deferredItem = $pool->getItem('save-vs-deferred-key');
        $deferredItem->set('old-deferred-value');
        $pool->saveDeferred($deferredItem);

        $explicitItem = $pool->getItem('save-vs-deferred-key');
        $explicitItem->set('new-explicit-value');
        $pool->save($explicitItem);

        $pool->commit();

        $this->assertEquals('new-explicit-value', $adapter->getItem('save-vs-deferred-key'));
    }

    public function testClearPurgesDeferredBuffer()
    {
        $pool = new CacheItemPool($adapter = new Memory());
        $item = $pool->getItem('clear-deferred-key');
        $item->set('value');
        $pool->saveDeferred($item);

        $pool->clear();
        $pool->commit();

        $this->assertFalse($adapter->hasItem('clear-deferred-key'));
    }

    public function testDeleteItemsPurgesDeferredBuffer()
    {
        $pool = new CacheItemPool($adapter = new Memory());
        $item = $pool->getItem('delete-items-deferred-key');
        $item->set('value');
        $pool->saveDeferred($item);

        $pool->deleteItems(['delete-items-deferred-key']);
        $pool->commit();

        $this->assertFalse($adapter->hasItem('delete-items-deferred-key'));
    }

    public function testSaveDeferredRejectsAForeignCacheItemInterfaceImplementation()
    {
        $pool = new CacheItemPool(new Memory());

        $foreignItem = new class implements \Psr\Cache\CacheItemInterface {
            public function getKey(): string { return 'foreign-deferred-key'; }
            public function get(): mixed { return 'value'; }
            public function isHit(): bool { return false; }
            public function set(mixed $value): static { return $this; }
            public function expiresAt(?\DateTimeInterface $expiration): static { return $this; }
            public function expiresAfter(int|\DateInterval|null $time): static { return $this; }
        };

        $this->expectException(InvalidArgumentException::class);
        $pool->saveDeferred($foreignItem);
    }

    public function testCommitOnEmptyDeferredBufferReturnsTrue()
    {
        $pool = new CacheItemPool(new Memory());
        $this->assertTrue($pool->commit());
    }

    public function testDestructorPersistsDeferredItemsWithoutExplicitCommit()
    {
        $adapter = new Memory();
        $pool    = new CacheItemPool($adapter);
        $item    = $pool->getItem('destructor-key');
        $item->set('destructor-value');
        $pool->saveDeferred($item);

        unset($pool);

        $this->assertEquals('destructor-value', $adapter->getItem('destructor-key'));
    }

    public function testDestructorSwallowsExceptionFromFailedCommit()
    {
        mkdir(__DIR__ . '/cache-destruct-fail');
        $adapter = new File(__DIR__ . '/cache-destruct-fail');
        $pool    = new CacheItemPool($adapter);
        $item    = $pool->getItem('deferred-key');
        $item->set('value');
        $pool->saveDeferred($item);

        // Force saveItem()'s mkdir to fail during the destructor-triggered commit(), by making the
        // shard directory's parent unwritable -- a real, non-mocked failure path. A destructor must
        // never let an exception escape, per PSR-6 §1.4.
        chmod(__DIR__ . '/cache-destruct-fail', 0555);

        try {
            unset($pool);
            $this->assertTrue(true);
        } finally {
            chmod(__DIR__ . '/cache-destruct-fail', 0755);
            $adapter->clear();
            $adapter->destroy();
        }
    }

}
