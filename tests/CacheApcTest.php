<?php

namespace Pop\Cache\Test;

use Pop\Cache\Adapter\Apc;
use PHPUnit\Framework\TestCase;

class CacheApcTest extends TestCase
{

    public function testConstructor()
    {
        $cache = new Apc(60);
        $this->assertInstanceOf('Pop\Cache\Adapter\Apc', $cache);
        $this->assertTrue(isset($cache->getInfo()['ttl']));
    }

    public function testSaveAndLoad()
    {
        $cache = new Apc(60);
        $cache->saveItem('foo', 'bar', 300);
        $this->assertEquals('bar', $cache->getItem('foo'));
        $this->assertEquals(300, $cache->getItemTtl('foo'));
        $this->assertTrue($cache->hasItem('foo'));
    }

    public function testGetExpiredItem()
    {
        $cache = new Apc(60);
        $cache->saveItem('foo', 'bar', 1);
        sleep(2);
        $this->assertFalse($cache->getItem('foo'));
        $cache->clear();
        $cache->destroy();
    }

    public function testRemove()
    {
        $cache = new Apc(60);
        $cache->saveItem('foo', 'bar');
        $this->assertEquals('bar', $cache->getItem('foo'));
        $cache->deleteItem('foo');
        $this->assertFalse($cache->getItem('foo'));
        $cache->clear();
        $cache->destroy();
    }

}