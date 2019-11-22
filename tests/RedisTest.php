<?php

namespace Pop\Cache\Test;

use Pop\Cache\Adapter\Redis;
use PHPUnit\Framework\TestCase;

class RedisTest extends TestCase
{

    public function testConstructor()
    {
        $cache = new Redis();
        $this->assertInstanceOf('Pop\Cache\Adapter\Redis', $cache);
        $this->assertInstanceOf('Redis', $cache->redis());
        $this->assertNotEmpty($cache->getVersion());
    }

    public function testSaveAndLoad()
    {
        $cache = new Redis();
        $cache->saveItem('foo', 'bar', 300);
        $this->assertEquals('bar', $cache->getItem('foo'));
        $this->assertEquals(300, $cache->getItemTtl('foo'));
        $this->assertTrue($cache->hasItem('foo'));
    }

    public function testGetExpiredItem()
    {
        $cache = new Redis();
        $cache->saveItem('foo', 'bar', 1);
        sleep(2);
        $this->assertFalse($cache->getItem('foo'));
        $cache->clear();
        $cache->destroy();
    }

    public function testRemove()
    {
        $cache = new Redis();
        $cache->saveItem('foo', 'bar');
        $this->assertEquals('bar', $cache->getItem('foo'));
        $cache->deleteItem('foo');
        $this->assertFalse($cache->getItem('foo'));
        $cache->clear();
        $cache->destroy();
    }

}