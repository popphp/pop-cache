<?php

namespace Pop\Cache\Test;

use Pop\Cache\Adapter\File;

class CacheFileTest extends \PHPUnit_Framework_TestCase
{

    public function testConstructor()
    {
        mkdir(__DIR__ . '/cache');
        $cache = new File(__DIR__ . '/cache');
        $this->assertInstanceOf('Pop\Cache\Adapter\File', $cache);
        $this->assertEquals(__DIR__ . '/cache', $cache->getDir());
        $cache->clear(true);
        $this->assertFalse(file_exists(__DIR__ . '/cache'));

    }

    public function testConstructorException()
    {
        $this->setExpectedException('Pop\Cache\Adapter\Exception');
        $cache = new File(__DIR__ . '/badcache');
    }

}