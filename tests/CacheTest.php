<?php

namespace Pop\Cache\Test;

use Pop\Cache\Cache;
use Pop\Cache\Adapter;

class CacheTest extends \PHPUnit_Framework_TestCase
{

    public function testConstructor()
    {
        mkdir(__DIR__ . '/cache');
        chmod(__DIR__ . '/cache', 0777);
        $cache = new Cache(new Adapter\File(__DIR__ . '/cache', 60));
        $this->assertInstanceOf('Pop\Cache\Cache', $cache);
        $this->assertInstanceOf('Pop\Cache\Adapter\File', $cache->adapter());
        $this->assertInstanceOf('Pop\Cache\Adapter\File', $cache->getAdapter());
        $this->assertTrue($cache->getAvailableAdapters()['file']);
        $this->assertTrue($cache->isAvailable('file'));
    }

    public function testSaveAndLoad()
    {
        $cache = new Cache(new Adapter\File(__DIR__ . '/cache', 60));
        $cache->save('foo', 'bar');
        $this->assertEquals('bar', $cache->load('foo'));
    }

    public function testRemove()
    {
        $cache = new Cache(new Adapter\File(__DIR__ . '/cache', 60));
        $cache->remove('foo');
        $this->assertFalse($cache->load('foo'));
    }

    public function testClear()
    {
        $cache1 = new Cache(new Adapter\File(__DIR__ . '/cache', 60));
        $cache1->save('foo', 'bar');
        $cache1->save('baz', 123);
        $this->assertEquals('bar', $cache1->load('foo'));
        $this->assertEquals(123, $cache1->load('baz'));
        $this->assertFalse($cache1->isExpired('foo'));
        $this->assertTrue(is_numeric($cache1->getStart('foo')));
        $this->assertTrue(is_numeric($cache1->getExpiration('foo')));
        $this->assertTrue(is_numeric($cache1->getLifetime('foo')));
        $cache1->clear();
        $this->assertFalse($cache1->load('foo'));
        $this->assertFalse($cache1->load('baz'));
        rmdir(__DIR__ . '/cache');

        $cache2 = new Cache(new Adapter\Apc());
        $cache2->save('foo', 'bar');
        $cache2->clear();
        $this->assertFalse($cache2->load('foo'));
    }

}