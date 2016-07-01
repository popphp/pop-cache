<?php

namespace Pop\Cache\Test;

use Pop\Cache\Adapter\Apc;

class CacheApcTest extends \PHPUnit_Framework_TestCase
{

    public function testConstructor()
    {
        if (version_compare(PHP_VERSION, 7.0) < 0) {
            $cache = new Apc(60);
            $this->assertInstanceOf('Pop\Cache\Adapter\Apc', $cache);
            $this->assertTrue(isset($cache->getInfo()['ttl']));
        }
    }

    public function testSaveAndLoad()
    {
        if (version_compare(PHP_VERSION, 7.0) < 0) {
            $cache = new Apc();
            $cache->save('foo', 'bar');
            $this->assertEquals('bar', $cache->load('foo'));
            $this->assertFalse($cache->isExpired('foo'));
            $this->assertTrue(is_numeric($cache->getStart('foo')));
            $this->assertTrue(is_numeric($cache->getExpiration('foo')));
            $this->assertTrue(is_numeric($cache->getLifetime('foo')));
        }
    }

    public function testRemove()
    {
        if (version_compare(PHP_VERSION, 7.0) < 0) {
            $cache = new Apc();
            $cache->save('foo', 'bar');
            $this->assertEquals('bar', $cache->load('foo'));
            $cache->remove('foo');
            $this->assertFalse($cache->load('foo'));
            $cache->clear();
        }
    }

}