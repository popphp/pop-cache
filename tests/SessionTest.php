<?php

namespace {
    ob_start();
}

namespace Pop\Cache\Test {

    use Pop\Cache\Adapter\Session;
    use PHPUnit\Framework\TestCase;

    class SessionTest extends TestCase
    {
        public function testConstructor()
        {
            $cache = new Session();
            $this->assertInstanceOf('Pop\Cache\Adapter\Session', $cache);
        }

        public function testSaveAndLoad()
        {
            $cache = new Session();
            $cache->saveItem('foo', 'bar', 300);
            $this->assertEquals('bar', $cache->getItem('foo'));
            $this->assertEquals(300, $cache->getItemTtl('foo'));
            $this->assertTrue($cache->hasItem('foo'));
        }

        public function testGetExpiredItem()
        {
            $cache = new Session();
            $cache->saveItem('foo', 'bar', 1);
            sleep(2);
            $this->assertFalse($cache->getItem('foo'));
            $cache->clear();
            $cache->destroy();
        }

        public function testRemove()
        {
            $cache = new Session();
            $cache->saveItem('foo', 'bar');
            $this->assertEquals('bar', $cache->getItem('foo'));
            $cache->deleteItem('foo');
            $this->assertFalse($cache->getItem('foo'));
            $cache->clear();
            $cache->destroy();
        }
    }

}

