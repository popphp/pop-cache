<?php

namespace Pop\Cache\Test;

use Pop\Cache\Adapter;
use Pop\Db\Db;
use PHPUnit\Framework\TestCase;

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

    public function testSaveAndLoad()
    {
        $cache = new Adapter\Database(Db::sqliteConnect(['database' => __DIR__ . '/tmp/cache.sqlite']));
        $cache->saveItem('foo', 'bar', 300);
        $this->assertEquals('bar', $cache->getItem('foo'));
        $this->assertEquals(300, $cache->getItemTtl('foo'));
        $this->assertTrue($cache->hasItem('foo'));
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

}