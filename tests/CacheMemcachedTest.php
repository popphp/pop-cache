<?php

namespace Pop\Cache\Test;

use Pop\Cache\Adapter\Memcached;

class CacheMemcachedTest extends \PHPUnit_Framework_TestCase
{

    public function testConstructor()
    {
        $cache = new Memcached();
        $this->assertInstanceOf('Pop\Cache\Adapter\Memcached', $cache);
        $this->assertNotNull($cache->getVersion());

    }

    public function testSaveAndLoad()
    {
        $cache = new Memcached();
        $cache->save('foo', 'bar', 0);
        $this->assertEquals('bar', $cache->load('foo', 0));
    }

    public function testRemove()
    {
        $cache = new Memcached();
        $cache->save('foo', 'bar', 0);
        $this->assertEquals('bar', $cache->load('foo', 0));
        $cache->remove('foo');
        $this->assertFalse($cache->load('foo', 0));
        $cache->clear();
    }

}