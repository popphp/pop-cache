<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Cache\Adapter;

use Pop\Cache\Clock;

/**
 * Cache adapter abstract class
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.0.0
 */
abstract class AbstractAdapter implements AdapterInterface
{

    /**
     * Global time-to-live
     * @var int
     */
    protected int $ttl = 0;

    /**
     * Clock used to resolve the current time
     * @var Clock\ClockInterface
     */
    protected Clock\ClockInterface $clock;

    /**
     * Constructor
     *
     * Instantiate the cache adapter object
     *
     * @param  int                  $ttl
     * @param  Clock\ClockInterface $clock
     */
    public function __construct(int $ttl = 0, Clock\ClockInterface $clock = new Clock\SystemClock())
    {
        $this->setTtl($ttl);
        $this->clock = $clock;
    }

    /**
     * Set the global time-to-live for the cache adapter
     *
     * @param  int $ttl
     * @return AbstractAdapter
     */
    public function setTtl(int $ttl): AbstractAdapter
    {
        $this->ttl = $ttl;
        return $this;
    }

    /**
     * Get the global time-to-live for the cache object
     *
     * @return int
     */
    public function getTtl(): int
    {
        return $this->ttl;
    }

    /**
     * Get the time-to-live for an item in cache
     *
     * @param  string $id
     * @param  int    $default
     * @return int
     */
    abstract public function getItemTtl(string $id, int $default = 0): int;

    /**
     * Save an item to cache
     *
     * @param  string $id
     * @param  mixed  $value
     * @param  ?int   $ttl
     * @return AbstractAdapter
     */
    abstract public function saveItem(string $id, mixed $value, ?int $ttl = null): AbstractAdapter;

    /**
     * Get an item from cache
     *
     * @param  string $id
     * @param  mixed  $default
     * @return mixed
     */
    abstract public function getItem(string $id, mixed $default = false): mixed;

    /**
     * Determine if the item exist in cache
     *
     * @param  string $id
     * @return bool
     */
    abstract public function hasItem(string $id): bool;

    /**
     * Delete a value in cache
     *
     * @param  string $id
     * @return AbstractAdapter
     */
    abstract public function deleteItem(string $id): AbstractAdapter;

    /**
     * Clear all stored values from cache
     *
     * @return AbstractAdapter
     */
    abstract public function clear(): AbstractAdapter;

    /**
     * Destroy cache resource
     *
     * @return AbstractAdapter
     */
    abstract public function destroy(): AbstractAdapter;

    /**
     * Atomically increment a counter in cache, creating it at $initial if it doesn't exist
     *
     * @param  string $id
     * @param  int    $amount
     * @param  int    $initial
     * @param  ?int   $ttl
     * @return int
     */
    abstract public function incrementItem(string $id, int $amount = 1, int $initial = 0, ?int $ttl = null): int;

    /**
     * Atomically decrement a counter in cache, creating it at $initial if it doesn't exist
     *
     * @param  string $id
     * @param  int    $amount
     * @param  int    $initial
     * @param  ?int   $ttl
     * @return int
     */
    abstract public function decrementItem(string $id, int $amount = 1, int $initial = 0, ?int $ttl = null): int;

}
