<?php

namespace Pop\Cache\Test;

use Pop\Cache\Cache;
use Pop\Cache\Adapter\File;
use Pop\Cache\Clock\MutableClock;
use PHPUnit\Framework\TestCase;

class FileCacheableStub
{
    public string $marker = 'should-not-reconstruct';
}

class FileTest extends TestCase
{

    public function testConstructor()
    {
        mkdir(__DIR__ . '/cache');
        $cache = new File(__DIR__ . '/cache');
        $this->assertInstanceOf('Pop\Cache\Adapter\File', $cache);
        $this->assertEquals(__DIR__ . '/cache', $cache->getDir());
        $cache->clear();
        $cache->destroy();
        $this->assertFalse(file_exists(__DIR__ . '/cache'));
    }

    public function testSaveItemLeavesNoTempFiles()
    {
        mkdir(__DIR__ . '/cache');
        $cache = new File(__DIR__ . '/cache');
        $cache->saveItem('foo', 'bar');

        $iterator  = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(__DIR__ . '/cache', \FilesystemIterator::SKIP_DOTS)
        );
        $tempFiles = [];
        foreach ($iterator as $file) {
            if (str_starts_with($file->getFilename(), '.tmp-')) {
                $tempFiles[] = $file->getPathname();
            }
        }

        $this->assertEmpty($tempFiles);
        $cache->clear();
        $cache->destroy();
    }

    public function testSavedItemIsSharded()
    {
        mkdir(__DIR__ . '/cache');
        $cache = new File(__DIR__ . '/cache');
        $cache->saveItem('foo', 'bar');

        $hash     = sha1('foo');
        $shardDir = substr($hash, 0, 2);

        $this->assertFileExists(__DIR__ . '/cache/' . $shardDir . '/' . $hash);
        $this->assertFileDoesNotExist(__DIR__ . '/cache/' . $hash);

        $cache->clear();
        $cache->destroy();
    }

    public function testClearRemovesShardedFilesAndDirectories()
    {
        mkdir(__DIR__ . '/cache');
        $cache = new File(__DIR__ . '/cache');
        $cache->saveItem('foo', 'bar');
        $cache->saveItem('baz', 'qux');

        $cache->clear();

        $remaining = array_diff(scandir(__DIR__ . '/cache'), ['.', '..']);
        $this->assertEmpty($remaining);

        $cache->destroy();
    }

    public function testClearDoesNotTouchUnrelatedSubdirectories()
    {
        mkdir(__DIR__ . '/cache');
        mkdir(__DIR__ . '/cache/not-a-shard-dir');
        file_put_contents(__DIR__ . '/cache/not-a-shard-dir/keep.txt', 'keep me');

        $cache = new File(__DIR__ . '/cache');
        $cache->saveItem('foo', 'bar');
        $cache->clear();

        $this->assertFileExists(__DIR__ . '/cache/not-a-shard-dir/keep.txt');

        unlink(__DIR__ . '/cache/not-a-shard-dir/keep.txt');
        rmdir(__DIR__ . '/cache/not-a-shard-dir');
        $cache->destroy();
    }

    public function testClearDeletesStrayTopLevelFiles()
    {
        mkdir(__DIR__ . '/cache');
        file_put_contents(__DIR__ . '/cache/stray.txt', 'not a sharded cache entry');

        $cache = new File(__DIR__ . '/cache');
        $cache->saveItem('foo', 'bar');
        $cache->clear();

        $this->assertFileDoesNotExist(__DIR__ . '/cache/stray.txt');

        $cache->destroy();
    }

    public function testClearOnAMissingDirectoryIsANoOp()
    {
        mkdir(__DIR__ . '/cache');
        $cache = new File(__DIR__ . '/cache');
        $cache->destroy();

        $this->assertFalse(file_exists(__DIR__ . '/cache'));

        // The directory is now gone; clear() must not error when it can't be opened.
        $cache->clear();
        $this->assertFalse(file_exists(__DIR__ . '/cache'));
    }

    public function testClearSkipsAShardDirectoryItCannotOpen()
    {
        mkdir(__DIR__ . '/cache');
        $cache = new File(__DIR__ . '/cache');
        $cache->saveItem('foo', 'bar');

        $shards    = array_values(array_diff(scandir(__DIR__ . '/cache'), ['.', '..']));
        $shardPath = __DIR__ . '/cache/' . $shards[0];

        // Execute+write but no read permission: is_dir() still succeeds (no read needed for stat),
        // but opendir() fails inside clearShard() -- the early-return branch under test.
        chmod($shardPath, 0311);

        try {
            $cache->clear();
            $this->assertTrue(true);
        } finally {
            chmod($shardPath, 0755);
            $cache->clear();
            $cache->destroy();
        }
    }

    public function testCachedObjectsComeBackAsIncompleteClass()
    {
        mkdir(__DIR__ . '/cache');
        $cache = new File(__DIR__ . '/cache');
        $cache->saveItem('object-test', new FileCacheableStub());
        $result = $cache->getItem('object-test');
        $this->assertInstanceOf('__PHP_Incomplete_Class', $result);
        $cache->clear();
        $cache->destroy();
    }

    public function testRememberWorksAgainstFileAdapter()
    {
        mkdir(__DIR__ . '/cache');
        $cache = new Cache(new File(__DIR__ . '/cache'));
        $calls = 0;

        $first  = $cache->remember('remember-file-key', function () use (&$calls) {
            $calls++;
            return 'computed-value';
        });
        $second = $cache->remember('remember-file-key', function () use (&$calls) {
            $calls++;
            return 'should-not-run';
        });

        $this->assertEquals('computed-value', $first);
        $this->assertEquals('computed-value', $second);
        $this->assertEquals(1, $calls);

        $cache->clear();
        $cache->destroy();
    }

    public function testGetExpiredItem()
    {
        mkdir(__DIR__ . '/cache');
        $cache = new File(__DIR__ . '/cache');
        $cache->saveItem('foo', 'bar', -1);
        $this->assertFalse($cache->getItem('foo'));
        $cache->clear();
        $cache->destroy();
        $this->assertFalse(file_exists(__DIR__ . '/cache'));
    }

    public function testGetItemDefaultDisambiguatesMissFromCachedFalse()
    {
        mkdir(__DIR__ . '/cache');
        $cache = new File(__DIR__ . '/cache');
        $cache->saveItem('default-test-flag', false);

        $this->assertFalse($cache->getItem('default-test-flag', 'MISS'));
        $this->assertEquals('MISS', $cache->getItem('default-test-nonexistent', 'MISS'));
        $this->assertFalse($cache->getItem('default-test-nonexistent-2'));

        $cache->clear();
        $cache->destroy();
    }

    public function testGetItemTtlDefaultDisambiguatesMissFromNeverExpires()
    {
        mkdir(__DIR__ . '/cache');
        $cache = new File(__DIR__ . '/cache', 0);
        $cache->saveItem('default-test-ttl', 'value');

        $this->assertEquals(0, $cache->getItemTtl('default-test-ttl', -1));
        $this->assertEquals(-1, $cache->getItemTtl('default-test-nonexistent', -1));
        $this->assertEquals(0, $cache->getItemTtl('default-test-nonexistent-2'));

        $cache->clear();
        $cache->destroy();
    }

    public function testExpirationUsesInjectedClock()
    {
        mkdir(__DIR__ . '/cache');
        $clock = new MutableClock(1000);
        $cache = new File(__DIR__ . '/cache', 0, $clock);
        $cache->saveItem('clock-test', 'value', 10);

        $this->assertEquals('value', $cache->getItem('clock-test'));

        $clock->advance(11);

        $this->assertFalse($cache->getItem('clock-test'));

        $cache->clear();
        $cache->destroy();
    }

    public function testConstructorException()
    {
        $this->expectException('Pop\Cache\Adapter\Exception');
        $cache = new File(__DIR__ . '/badcache');
    }

    public function testConstructorThrowsWhenDirectoryNotWritable()
    {
        mkdir(__DIR__ . '/cache-readonly');
        chmod(__DIR__ . '/cache-readonly', 0555);

        try {
            $this->expectException('Pop\Cache\Adapter\Exception');
            new File(__DIR__ . '/cache-readonly');
        } finally {
            chmod(__DIR__ . '/cache-readonly', 0755);
            rmdir(__DIR__ . '/cache-readonly');
        }
    }

    public function testIncrementItemCreatesAtInitialThenApplies()
    {
        mkdir(__DIR__ . '/cache');
        $cache  = new File(__DIR__ . '/cache');
        $result = $cache->incrementItem('counter', 5, 100);

        $this->assertEquals(105, $result);
        $this->assertEquals(105, $cache->getItem('counter'));

        $cache->clear();
        $cache->destroy();
    }

    public function testDecrementItemAllowsNegativeResult()
    {
        mkdir(__DIR__ . '/cache');
        $cache  = new File(__DIR__ . '/cache');
        $result = $cache->decrementItem('counter', 10, 5);

        $this->assertEquals(-5, $result);

        $cache->clear();
        $cache->destroy();
    }

    public function testIncrementItemOnNonNumericValueThrows()
    {
        mkdir(__DIR__ . '/cache');
        $cache = new File(__DIR__ . '/cache');
        $cache->saveItem('not-a-number', 'hello');

        try {
            $this->expectException(\Pop\Cache\Adapter\Exception::class);
            $cache->incrementItem('not-a-number');
        } finally {
            $cache->clear();
            $cache->destroy();
        }
    }

    public function testDecrementItemOnNonNumericValueThrows()
    {
        mkdir(__DIR__ . '/cache');
        $cache = new File(__DIR__ . '/cache');
        $cache->saveItem('not-a-number', 'hello');

        try {
            $this->expectException(\Pop\Cache\Adapter\Exception::class);
            $cache->decrementItem('not-a-number');
        } finally {
            $cache->clear();
            $cache->destroy();
        }
    }

    public function testIncrementItemWithZeroAmountPeeksWithoutMutating()
    {
        mkdir(__DIR__ . '/cache');
        $cache = new File(__DIR__ . '/cache');
        $cache->incrementItem('counter', 1, 50);
        $peek = $cache->incrementItem('counter', 0);

        $this->assertEquals(51, $peek);

        $cache->clear();
        $cache->destroy();
    }

    public function testTaggingViaCacheFacade()
    {
        mkdir(__DIR__ . '/cache');
        $adapter = new File(__DIR__ . '/cache');
        $cache   = new Cache($adapter);

        $cache->saveTaggedItem('item1', 'v1', ['tagA']);
        $this->assertEquals('v1', $cache->getItem('item1'));

        $cache->invalidateTag('tagA');
        $this->assertFalse($cache->getItem('item1'));

        $adapter->clear();
        $adapter->destroy();
    }

    public function testRememberStampedeProtectionViaCacheFacade()
    {
        mkdir(__DIR__ . '/cache');
        $adapter = new File(__DIR__ . '/cache');
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
        $this->assertTrue($adapter->hasItem('@remember:' . sha1('stampede-key')));

        $adapter->clear();
        $adapter->destroy();
    }

}