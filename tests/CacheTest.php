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
        $this->assertEquals(60, $cache->getTtl());
        $this->assertInstanceOf('Pop\Cache\Cache', $cache);
        $this->assertInstanceOf('Pop\Cache\Adapter\File', $cache->adapter());
        $this->assertTrue($cache->getAvailableAdapters()['file']);
        $this->assertTrue($cache->isAvailable('file'));
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
        $this->assertFalse($cache1->getItem('foo'));
        $this->assertFalse($cache1->getItem('baz'));
        rmdir(__DIR__ . '/cache');
    }

}