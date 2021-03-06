<?php

namespace Pop\Cache\Test;

use Pop\Cache\Adapter\Memcached;
use PHPUnit\Framework\TestCase;

class MemcachedTest extends TestCase
{

    public function testConstructor()
    {
        $cache = new Memcached();
        $this->assertInstanceOf('Pop\Cache\Adapter\Memcached', $cache);
        $this->assertInstanceOf('Memcached', $cache->memcached());
        $this->assertNotEmpty($cache->getVersion());
        $cache->addServers([]);
    }

    public function testSaveAndLoad()
    {
        $cache = new Memcached();
        $cache->saveItem('foo', 'bar', 300);
        $this->assertEquals('bar', $cache->getItem('foo'));
        $this->assertEquals(300, $cache->getItemTtl('foo'));
        $this->assertTrue($cache->hasItem('foo'));
    }

    public function testGetExpiredItem()
    {
        $cache = new Memcached();
        $cache->saveItem('foo', 'bar', 1);
        sleep(2);
        $this->assertFalse($cache->getItem('foo'));
        $cache->clear();
        $cache->destroy();
    }

    public function testRemove()
    {
        $cache = new Memcached();
        $cache->saveItem('foo', 'bar');
        $this->assertEquals('bar', $cache->getItem('foo'));
        $cache->deleteItem('foo');
        $this->assertFalse($cache->getItem('foo'));
        $cache->clear();
        $cache->destroy();
    }

}