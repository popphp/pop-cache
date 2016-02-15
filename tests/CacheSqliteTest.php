<?php

namespace Pop\Cache\Test;

use Pop\Cache\Adapter\Sqlite;

class CacheSqliteTest extends \PHPUnit_Framework_TestCase
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

    public function testSaveAndLoad()
    {
        $cache = new Sqlite(__DIR__ . '/cache/cache.sqlite');
        $cache->save('foo', 'bar');
        $this->assertEquals('bar', $cache->load('foo'));
    }

    public function testRemove()
    {
        $cache = new Sqlite(__DIR__ . '/cache/cache.sqlite');
        $cache->save('foo', 'bar');
        $this->assertEquals('bar', $cache->load('foo'));
        $cache->remove('foo');
        $this->assertFalse($cache->load('foo'));
        $cache->clear();
        $cache->delete();
        $this->assertFalse(file_exists(__DIR__ . '/cache/cache.sqlite'));
        rmdir(__DIR__ . '/cache');
    }

}