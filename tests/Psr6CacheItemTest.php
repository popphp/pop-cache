<?php

namespace Pop\Cache\Test;

use Pop\Cache\Psr6\CacheItem;
use PHPUnit\Framework\TestCase;

class Psr6CacheItemTest extends TestCase
{

    public function testConstructorDefaults()
    {
        $item = new CacheItem('foo');
        $this->assertEquals('foo', $item->getKey());
        $this->assertNull($item->get());
        $this->assertFalse($item->isHit());
    }

    public function testConstructorWithValueAndHit()
    {
        $item = new CacheItem('foo', 'bar', true);
        $this->assertEquals('foo', $item->getKey());
        $this->assertEquals('bar', $item->get());
        $this->assertTrue($item->isHit());
    }

    public function testSetIsFluentAndUpdatesValue()
    {
        $item   = new CacheItem('foo');
        $result = $item->set('new-value');
        $this->assertSame($item, $result);
        $this->assertEquals('new-value', $item->get());
    }

    public function testGetExpirationSecondsDefaultsToNull()
    {
        $item = new CacheItem('foo');
        $this->assertNull($item->getExpirationSeconds());
    }

    public function testExpiresAtWithNullMeansUseDefault()
    {
        $item = new CacheItem('foo');
        $item->expiresAfter(300);
        $item->expiresAt(null);
        $this->assertNull($item->getExpirationSeconds());
    }

    public function testExpiresAtWithFutureDateResolvesToPositiveSeconds()
    {
        $item       = new CacheItem('foo');
        $expiration = new \DateTimeImmutable('+300 seconds');
        $item->expiresAt($expiration);
        $this->assertEqualsWithDelta(300, $item->getExpirationSeconds(), 2);
    }

    public function testExpiresAtWithPastDateResolvesToZeroNotNegative()
    {
        $item       = new CacheItem('foo');
        $expiration = new \DateTimeImmutable('-300 seconds');
        $item->expiresAt($expiration);
        $this->assertEquals(0, $item->getExpirationSeconds());
    }

    public function testExpiresAfterWithNullMeansUseDefault()
    {
        $item = new CacheItem('foo');
        $item->expiresAfter(300);
        $item->expiresAfter(null);
        $this->assertNull($item->getExpirationSeconds());
    }

    public function testExpiresAfterWithIntSeconds()
    {
        $item = new CacheItem('foo');
        $item->expiresAfter(300);
        $this->assertEquals(300, $item->getExpirationSeconds());
    }

    public function testExpiresAfterWithNegativeIntSeconds()
    {
        $item = new CacheItem('foo');
        $item->expiresAfter(-10);
        $this->assertEquals(-10, $item->getExpirationSeconds());
    }

    public function testExpiresAfterWithDateInterval()
    {
        $item = new CacheItem('foo');
        $item->expiresAfter(new \DateInterval('PT300S'));
        $this->assertEquals(300, $item->getExpirationSeconds());
    }

}
