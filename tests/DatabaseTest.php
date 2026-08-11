<?php

namespace Pop\Cache\Test;

use Pop\Cache\Cache;
use Pop\Cache\Adapter;
use Pop\Cache\Clock\MutableClock;
use Pop\Db\Db;
use PHPUnit\Framework\TestCase;

class DatabaseCacheableStub
{
    public string $marker = 'should-not-reconstruct';
}

class DatabaseTest extends TestCase
{

    public function testConstructor()
    {
        chmod(__DIR__ . '/tmp', 0777);
        touch(__DIR__ . '/tmp/cache.sqlite');
        chmod(__DIR__ . '/tmp/cache.sqlite', 0777);
        $cache = new Adapter\Database(Db::sqliteConnect(['database' => __DIR__ . '/tmp/cache.sqlite']));
        $this->assertInstanceOf('Pop\Cache\Adapter\Database', $cache);
        $this->assertInstanceOf('Pop\Db\Adapter\Sqlite', $cache->getDb());
        $this->assertEquals('pop_cache', $cache->getTable());
    }

    public function testTableHasUniqueKeyIndex()
    {
        chmod(__DIR__ . '/tmp', 0777);
        touch(__DIR__ . '/tmp/cache_unique.sqlite');
        chmod(__DIR__ . '/tmp/cache_unique.sqlite', 0777);
        $db = Db::sqliteConnect(['database' => __DIR__ . '/tmp/cache_unique.sqlite']);
        new Adapter\Database($db, 0, 'pop_cache_unique');

        $db->query('INSERT INTO pop_cache_unique ("key", start, ttl, value) VALUES (\'dupe\', 0, 0, \'a\')');

        $threw = false;
        try {
            // Suppressed: SQLite3::query() emits a native PHP warning on the constraint violation this
            // insert deliberately triggers (see pop-db's Sqlite::query(), which calls SQLite3::query()
            // directly before converting the failure into the typed exception caught below). The warning
            // is an expected side effect of proving the unique constraint works, not a real problem.
            @$db->query('INSERT INTO pop_cache_unique ("key", start, ttl, value) VALUES (\'dupe\', 0, 0, \'b\')');
        } catch (\Pop\Db\Adapter\Exception $e) {
            $threw = true;
        }

        $this->assertTrue($threw);
        unlink(__DIR__ . '/tmp/cache_unique.sqlite');
    }

    public function testSaveAndLoad()
    {
        $cache = new Adapter\Database(Db::sqliteConnect(['database' => __DIR__ . '/tmp/cache.sqlite']));
        $cache->saveItem('foo', 'bar', 300);
        $this->assertEquals('bar', $cache->getItem('foo'));
        $this->assertEquals(300, $cache->getItemTtl('foo'));
        $this->assertTrue($cache->hasItem('foo'));
    }

    public function testCachedObjectsComeBackAsIncompleteClass()
    {
        $cache = new Adapter\Database(Db::sqliteConnect(['database' => __DIR__ . '/tmp/cache.sqlite']));
        $cache->saveItem('object-test', new DatabaseCacheableStub());
        $result = $cache->getItem('object-test');
        $this->assertInstanceOf('__PHP_Incomplete_Class', $result);
    }

    public function testGetItemDefaultDisambiguatesMissFromCachedFalse()
    {
        $cache = new Adapter\Database(Db::sqliteConnect(['database' => __DIR__ . '/tmp/cache.sqlite']));
        $cache->saveItem('default-test-flag', false);

        $this->assertFalse($cache->getItem('default-test-flag', 'MISS'));
        $this->assertEquals('MISS', $cache->getItem('default-test-nonexistent', 'MISS'));
        $this->assertFalse($cache->getItem('default-test-nonexistent-2'));
    }

    public function testGetItemTtlDefaultDisambiguatesMissFromNeverExpires()
    {
        $cache = new Adapter\Database(Db::sqliteConnect(['database' => __DIR__ . '/tmp/cache.sqlite']));
        $cache->saveItem('default-test-ttl', 'value');

        $this->assertEquals(0, $cache->getItemTtl('default-test-ttl', -1));
        $this->assertEquals(-1, $cache->getItemTtl('default-test-nonexistent', -1));
        $this->assertEquals(0, $cache->getItemTtl('default-test-nonexistent-2'));
    }

    public function testExpirationUsesInjectedClock()
    {
        chmod(__DIR__ . '/tmp', 0777);
        touch(__DIR__ . '/tmp/cache_clock.sqlite');
        chmod(__DIR__ . '/tmp/cache_clock.sqlite', 0777);
        $clock = new MutableClock(1000);
        $cache = new Adapter\Database(
            Db::sqliteConnect(['database' => __DIR__ . '/tmp/cache_clock.sqlite']), 0, 'pop_cache', $clock
        );
        $cache->saveItem('clock-test', 'value', 10);

        $this->assertEquals('value', $cache->getItem('clock-test'));

        $clock->advance(11);

        $this->assertFalse($cache->getItem('clock-test'));

        unlink(__DIR__ . '/tmp/cache_clock.sqlite');
    }

    public function testSaveItemTwiceUpdatesSingleRow()
    {
        chmod(__DIR__ . '/tmp', 0777);
        touch(__DIR__ . '/tmp/cache_upsert.sqlite');
        chmod(__DIR__ . '/tmp/cache_upsert.sqlite', 0777);
        $db    = Db::sqliteConnect(['database' => __DIR__ . '/tmp/cache_upsert.sqlite']);
        $cache = new Adapter\Database($db, 0, 'pop_cache_upsert');

        $cache->saveItem('foo', 'first', 100);
        $cache->saveItem('foo', 'second', 200);

        $this->assertEquals('second', $cache->getItem('foo'));
        $this->assertEquals(200, $cache->getItemTtl('foo'));

        $sql = $db->createSql();
        $sql->select()->from('pop_cache_upsert');
        $db->prepare($sql)->execute();
        $rows = $db->fetchAll();
        $this->assertCount(1, $rows);

        unlink(__DIR__ . '/tmp/cache_upsert.sqlite');
    }

    public function testGetExpiredItem()
    {
        $cache = new Adapter\Database(Db::sqliteConnect(['database' => __DIR__ . '/tmp/cache.sqlite']));
        $cache->saveItem('foo', 'bar', -1);
        $this->assertFalse($cache->getItem('foo'));
        $cache->clear();
        $cache->destroy();
    }

    public function testRemove()
    {
        $cache = new Adapter\Database(Db::sqliteConnect(['database' => __DIR__ . '/tmp/cache.sqlite']));
        $cache->saveItem('foo', 'bar');
        $this->assertEquals('bar', $cache->getItem('foo'));
        $cache->deleteItem('foo');
        $this->assertFalse($cache->getItem('foo'));
        $cache->clear();
        $cache->destroy();
        $this->assertFalse(file_exists(__DIR__ . '/cache/cache.sqlite'));
        unlink(__DIR__ . '/tmp/cache.sqlite');
    }

    public function testMultipleDatabaseAdaptersWithDifferentTableNamesOnSameConnection()
    {
        chmod(__DIR__ . '/tmp', 0777);
        touch(__DIR__ . '/tmp/cache_multi.sqlite');
        chmod(__DIR__ . '/tmp/cache_multi.sqlite', 0777);
        $db = Db::sqliteConnect(['database' => __DIR__ . '/tmp/cache_multi.sqlite']);

        // Create first Database adapter with table_a
        $cache1 = new Adapter\Database($db, 0, 'table_a');
        $this->assertEquals('table_a', $cache1->getTable());

        // Create second Database adapter with table_b on the same connection
        // This should not throw an exception about index name collision
        $cache2 = new Adapter\Database($db, 0, 'table_b');
        $this->assertEquals('table_b', $cache2->getTable());

        // Verify both adapters work
        $cache1->saveItem('key1', 'value1', 300);
        $cache2->saveItem('key2', 'value2', 300);

        $this->assertEquals('value1', $cache1->getItem('key1'));
        $this->assertEquals('value2', $cache2->getItem('key2'));

        unlink(__DIR__ . '/tmp/cache_multi.sqlite');
    }

    public function testIncrementItemCreatesAtInitialThenApplies()
    {
        chmod(__DIR__ . '/tmp', 0777);
        touch(__DIR__ . '/tmp/cache_incr.sqlite');
        chmod(__DIR__ . '/tmp/cache_incr.sqlite', 0777);
        $cache = new Adapter\Database(Db::sqliteConnect(['database' => __DIR__ . '/tmp/cache_incr.sqlite']));

        try {
            $result = $cache->incrementItem('counter', 5, 100);

            $this->assertEquals(105, $result);
            $this->assertEquals(105, $cache->getItem('counter'));
        } finally {
            unlink(__DIR__ . '/tmp/cache_incr.sqlite');
        }
    }

    public function testDecrementItemAllowsNegativeResult()
    {
        chmod(__DIR__ . '/tmp', 0777);
        touch(__DIR__ . '/tmp/cache_decr.sqlite');
        chmod(__DIR__ . '/tmp/cache_decr.sqlite', 0777);
        $cache = new Adapter\Database(Db::sqliteConnect(['database' => __DIR__ . '/tmp/cache_decr.sqlite']));

        try {
            $result = $cache->decrementItem('counter', 10, 5);

            $this->assertEquals(-5, $result);
        } finally {
            unlink(__DIR__ . '/tmp/cache_decr.sqlite');
        }
    }

    public function testIncrementItemOnNonNumericValueThrows()
    {
        chmod(__DIR__ . '/tmp', 0777);
        touch(__DIR__ . '/tmp/cache_nonnum.sqlite');
        chmod(__DIR__ . '/tmp/cache_nonnum.sqlite', 0777);
        $cache = new Adapter\Database(Db::sqliteConnect(['database' => __DIR__ . '/tmp/cache_nonnum.sqlite']));
        $cache->saveItem('not-a-number', 'hello');

        try {
            $this->expectException(\Pop\Cache\Adapter\Exception::class);
            $cache->incrementItem('not-a-number');
        } finally {
            unlink(__DIR__ . '/tmp/cache_nonnum.sqlite');
        }
    }

    public function testDecrementItemOnNonNumericValueThrows()
    {
        chmod(__DIR__ . '/tmp', 0777);
        touch(__DIR__ . '/tmp/cache_nonnum_decr.sqlite');
        chmod(__DIR__ . '/tmp/cache_nonnum_decr.sqlite', 0777);
        $cache = new Adapter\Database(Db::sqliteConnect(['database' => __DIR__ . '/tmp/cache_nonnum_decr.sqlite']));
        $cache->saveItem('not-a-number', 'hello');

        try {
            $this->expectException(\Pop\Cache\Adapter\Exception::class);
            $cache->decrementItem('not-a-number');
        } finally {
            unlink(__DIR__ . '/tmp/cache_nonnum_decr.sqlite');
        }
    }

    public function testIncrementItemWithZeroAmountPeeksWithoutMutating()
    {
        chmod(__DIR__ . '/tmp', 0777);
        touch(__DIR__ . '/tmp/cache_peek.sqlite');
        chmod(__DIR__ . '/tmp/cache_peek.sqlite', 0777);
        $cache = new Adapter\Database(Db::sqliteConnect(['database' => __DIR__ . '/tmp/cache_peek.sqlite']));

        try {
            $cache->incrementItem('counter', 1, 50);

            $peek = $cache->incrementItem('counter', 0);

            $this->assertEquals(51, $peek);
        } finally {
            unlink(__DIR__ . '/tmp/cache_peek.sqlite');
        }
    }

    public function testTaggingViaCacheFacade()
    {
        chmod(__DIR__ . '/tmp', 0777);
        touch(__DIR__ . '/tmp/cache_tag.sqlite');
        chmod(__DIR__ . '/tmp/cache_tag.sqlite', 0777);
        $adapter = new Adapter\Database(Db::sqliteConnect(['database' => __DIR__ . '/tmp/cache_tag.sqlite']));
        $cache   = new Cache($adapter);

        try {
            $cache->saveTaggedItem('item1', 'v1', ['tagA']);
            $this->assertEquals('v1', $cache->getItem('item1'));

            $cache->invalidateTag('tagA');
            $this->assertFalse($cache->getItem('item1'));
        } finally {
            unlink(__DIR__ . '/tmp/cache_tag.sqlite');
        }
    }

    public function testRememberStampedeProtectionViaCacheFacade()
    {
        chmod(__DIR__ . '/tmp', 0777);
        touch(__DIR__ . '/tmp/cache_remember.sqlite');
        chmod(__DIR__ . '/tmp/cache_remember.sqlite', 0777);
        $adapter = new Adapter\Database(Db::sqliteConnect(['database' => __DIR__ . '/tmp/cache_remember.sqlite']));
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
            unlink(__DIR__ . '/tmp/cache_remember.sqlite');
        }
    }

    public function testMysqlDriverPlaceholderAndUpsertPaths()
    {
        $db = Db::mysqlConnect([
            'database' => $_ENV['MYSQL_DB'],
            'username' => $_ENV['MYSQL_USER'],
            'password' => $_ENV['MYSQL_PASS'],
            'host'     => $_ENV['MYSQL_HOST'],
        ]);
        $cache = new Adapter\Database($db, 0, 'pop_cache_mysql_test');

        try {
            $cache->saveItem('foo', 'bar', 300);
            $this->assertEquals('bar', $cache->getItem('foo'));
            $this->assertEquals(300, $cache->getItemTtl('foo'));
            $this->assertTrue($cache->hasItem('foo'));

            // Re-save the same key: exercises the mysql-specific upsert (onDuplicateKeyUpdate) path.
            $cache->saveItem('foo', 'baz', 300);
            $this->assertEquals('baz', $cache->getItem('foo'));

            $cache->deleteItem('foo');
            $this->assertFalse($cache->hasItem('foo'));
            $this->assertFalse($cache->getItem('foo'));
        } finally {
            $db->query('DROP TABLE IF EXISTS pop_cache_mysql_test');
        }
    }

    public function testPgsqlDriverPlaceholderAndUpsertPaths()
    {
        $db = Db::pgsqlConnect([
            'database' => $_ENV['PGSQL_DB'],
            'username' => $_ENV['PGSQL_USER'],
            'password' => $_ENV['PGSQL_PASS'],
            'host'     => $_ENV['PGSQL_HOST'],
        ]);
        $cache = new Adapter\Database($db, 0, 'pop_cache_pgsql_test');

        try {
            $cache->saveItem('foo', 'bar', 300);
            $this->assertEquals('bar', $cache->getItem('foo'));
            $this->assertEquals(300, $cache->getItemTtl('foo'));
            $this->assertTrue($cache->hasItem('foo'));

            // Re-save the same key: exercises the pgsql-specific upsert (onConflict) path.
            $cache->saveItem('foo', 'baz', 300);
            $this->assertEquals('baz', $cache->getItem('foo'));

            $cache->deleteItem('foo');
            $this->assertFalse($cache->hasItem('foo'));
            $this->assertFalse($cache->getItem('foo'));
        } finally {
            $db->query('DROP TABLE IF EXISTS pop_cache_pgsql_test');
        }
    }

}