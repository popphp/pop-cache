<?php

namespace Pop\Cache\Test;

use Pop\Cache\Adapter\Sqlite;
use PHPUnit\Framework\TestCase;

class CacheSqliteTest extends TestCase
{

    public function testConstructor()
    {
        mkdir(__DIR__ . '/cache');
        chmod(__DIR__ . '/cache', 0777);
        touch(__DIR__ . '/cache/cache.sqlite');
        chmod(__DIR__ . '/cache/cache.sqlite', 0777);
        $cache = new Sqlite(__DIR__ . '/cache/cache.sqlite');
        $this->assertInstanceOf('Pop\Cache\Adapter\Sqlite', $cache);
        $this->assertEquals(__DIR__ . '/cache/cache.sqlite', $cache->getDb());
        $this->assertEquals('pop_cache', $cache->getTable());
    }

    public function testConstructorWithPdo()
    {
        $cache = new Sqlite(__DIR__ . '/cache/cache.sqlite', 0, 'pop_cache', true);
        $this->assertInstanceOf('Pop\Cache\Adapter\Sqlite', $cache);
        $cache->saveItem('baz', 'baz', 300);
        $this->assertEquals('baz', $cache->getItem('baz'));
        $this->assertEquals(300, $cache->getItemTtl('baz'));
        $this->assertTrue($cache->hasItem('baz'));
    }

    public function testSaveAndLoad()
    {
        $cache = new Sqlite(__DIR__ . '/cache/cache.sqlite');
        $cache->saveItem('foo', 'bar', 300);
        $this->assertEquals('bar', $cache->getItem('foo'));
        $this->assertEquals(300, $cache->getItemTtl('foo'));
        $this->assertTrue($cache->hasItem('foo'));
    }

    public function testGetExpiredItem()
    {
        $cache = new Sqlite(__DIR__ . '/cache/cache.sqlite');
        $cache->saveItem('foo', 'bar', -1);
        $this->assertFalse($cache->getItem('foo'));
        $cache->clear();
        $cache->destroy();
    }

    public function testRemove()
    {
        $cache = new Sqlite(__DIR__ . '/cache/cache.sqlite');
        $cache->saveItem('foo', 'bar');
        $this->assertEquals('bar', $cache->getItem('foo'));
        $cache->deleteItem('foo');
        $this->assertFalse($cache->getItem('foo'));
        $cache->clear();
        $cache->destroy();
        $this->assertFalse(file_exists(__DIR__ . '/cache/cache.sqlite'));
        rmdir(__DIR__ . '/cache');
    }

}