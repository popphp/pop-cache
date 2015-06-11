<?php

namespace Pop\Acl\Test;

use Pop\Cache\Cache;
use Pop\Cache\Adapter;

class CacheTest extends \PHPUnit_Framework_TestCase
{

    public function testConstructor()
    {
        mkdir(__DIR__ . '/cache');
        $cache = new Cache(new Adapter\File(__DIR__ . '/cache'));
        $cache->setLifetime(60);
        $this->assertInstanceOf('Pop\Cache\Cache', $cache);
        $this->assertInstanceOf('Pop\Cache\Adapter\File', $cache->adapter());
        $this->assertEquals(60, $cache->getLifetime());
        $this->assertTrue($cache->getAvailableAdapters()['file']);
        $this->assertTrue($cache->isAvailable('file'));
    }

    public function testSaveAndLoad()
    {
        $cache = new Cache(new Adapter\File(__DIR__ . '/cache'));
        $cache->save('foo', 'bar');
        $this->assertEquals('bar', $cache->load('foo'));
    }

    public function testRemove()
    {
        $cache = new Cache(new Adapter\File(__DIR__ . '/cache'));
        $cache->remove('foo');
        $this->assertFalse($cache->load('foo'));
    }

    public function testClear()
    {
        $cache = new Cache(new Adapter\File(__DIR__ . '/cache'));
        $cache->save('foo', 'bar');
        $cache->save('baz', 123);
        $this->assertEquals('bar', $cache->load('foo'));
        $this->assertEquals(123, $cache->load('baz'));
        $cache->clear();
        $this->assertFalse($cache->load('foo'));
        $this->assertFalse($cache->load('baz'));
        rmdir(__DIR__ . '/cache');
    }

}