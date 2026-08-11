<?php

namespace Pop\Cache\Test;

use Pop\Cache\Adapter\AbstractAdapter;
use Pop\Cache\Clock\ClockInterface;
use Pop\Cache\Clock\MutableClock;
use Pop\Cache\Clock\SystemClock;
use PHPUnit\Framework\TestCase;

class AbstractAdapterTest extends TestCase
{

    protected function makeAdapter(int $ttl = 0, ?ClockInterface $clock = null): AbstractAdapter
    {
        return new class($ttl, $clock ?? new SystemClock()) extends AbstractAdapter {
            public function getItemTtl(string $id, int $default = 0): int
            {
                return $default;
            }
            public function saveItem(string $id, mixed $value, ?int $ttl = null): AbstractAdapter
            {
                return $this;
            }
            public function getItem(string $id, mixed $default = false): mixed
            {
                return $default;
            }
            public function hasItem(string $id): bool
            {
                return false;
            }
            public function deleteItem(string $id): AbstractAdapter
            {
                return $this;
            }
            public function clear(): AbstractAdapter
            {
                return $this;
            }
            public function destroy(): AbstractAdapter
            {
                return $this;
            }
            public function incrementItem(string $id, int $amount = 1, int $initial = 0, ?int $ttl = null): int
            {
                return $initial + $amount;
            }
            public function decrementItem(string $id, int $amount = 1, int $initial = 0, ?int $ttl = null): int
            {
                return $initial - $amount;
            }
        };
    }

    public function testConstructorDefaultsToSystemClock()
    {
        $adapter  = $this->makeAdapter();
        $property = new \ReflectionProperty($adapter, 'clock');
        $property->setAccessible(true);

        $this->assertInstanceOf(SystemClock::class, $property->getValue($adapter));
    }

    public function testConstructorAcceptsAnExplicitClock()
    {
        $clock    = new MutableClock(1000);
        $adapter  = $this->makeAdapter(0, $clock);
        $property = new \ReflectionProperty($adapter, 'clock');
        $property->setAccessible(true);

        $this->assertSame($clock, $property->getValue($adapter));
    }

}
