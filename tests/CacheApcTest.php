<?php

namespace Pop\Cache\Test;

use Pop\Cache\Adapter\Apc;

class CacheApcTest extends \PHPUnit_Framework_TestCase
{

    public function testConstructor()
    {
        $cache = new Apc(60);
        $this->assertInstanceOf('Pop\Cache\Adapter\Apc', $cache);
        $this->assertTrue(isset($cache->getInfo()['ttl']));
    }

    public function testSaveAndLoad()
    {
        $cache = new Apc();
        $cache->save('foo', 'bar');
        $this->assertEquals('bar', $cache->load('foo'));
    }

    public function testRemove()
    {
        $cache = new Apc();
        $cache->save('foo', 'bar');
        $this->assertEquals('bar', $cache->load('foo'));
        $cache->remove('foo');
        $this->assertFalse($cache->load('foo'));
        $cache->clear();
    }

}