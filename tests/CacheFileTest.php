<?php

namespace Pop\Cache\Test;

use Pop\Cache\Adapter\File;
use PHPUnit\Framework\TestCase;

class CacheFileTest extends TestCase
{

    public function testConstructor()
    {
        mkdir(__DIR__ . '/cache');
        $cache = new File(__DIR__ . '/cache');
        $this->assertInstanceOf('Pop\Cache\Adapter\File', $cache);
        $this->assertEquals(__DIR__ . '/cache', $cache->getDir());
        $cache->clear();
        $cache->destroy();
        $this->assertFalse(file_exists(__DIR__ . '/cache'));
    }

    public function testGetExpiredItem()
    {
        mkdir(__DIR__ . '/cache');
        $cache = new File(__DIR__ . '/cache');
        $cache->saveItem('foo', 'bar', -1);
        $this->assertFalse($cache->getItem('foo'));
        $cache->clear();
        $cache->destroy();
        $this->assertFalse(file_exists(__DIR__ . '/cache'));
    }

    public function testConstructorException()
    {
        $this->expectException('Pop\Cache\Adapter\Exception');
        $cache = new File(__DIR__ . '/badcache');
    }

}