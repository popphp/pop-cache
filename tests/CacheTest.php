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

    public function testMagicMethods()
    {
        $cache = new Cache(new Adapter\File(__DIR__ . '/cache', 60));
        $cache->baz     = 789;
        $cache['test3'] = 999;
        $this->assertTrue(isset($cache->baz));
        $this->assertTrue(isset($cache['test3']));
        unset($cache->baz);
        unset($cache['test3']);
        $this->assertFalse(isset($cache->baz));
        $this->assertFalse(isset($cache['test3']));
    }

    public function testSaveAndDeleteItems()
    {
        $cache = new Cache(new Adapter\File(__DIR__ . '/cache', 60));
        $cache->saveItems([
            'test1' => 123,
            'test2' => 456
        ]);
        $this->assertEquals(123, $cache->getItem('test1'));
        $this->assertEquals(456, $cache->getItem('test2'));
        $cache->deleteItems(['test1', 'test2']);
        $this->assertFalse($cache->getItem('test1'));
        $this->assertFalse($cache->getItem('test2'));
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
        $cache1->destroy();
        $this->assertFalse($cache1->getItem('foo'));
        $this->assertFalse($cache1->getItem('baz'));
        if (file_exists(__DIR__ . '/cache')) {
            rmdir(__DIR__ . '/cache');
        }
    }

}