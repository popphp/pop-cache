<?php
declare(strict_types=1);
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

/**
 * Memory adapter cache class
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.0.0
 */
class Memory extends AbstractAdapter
{

    /**
     * Cached items, keyed by sha1($id)
     * @var array
     */
    protected array $items = [];

    /**
     * Get the time-to-live for an item in cache
     *
     * @param  string $id
     * @param  int    $default
     * @return int
     */
    public function getItemTtl(string $id, int $default = 0): int
    {
        $key = sha1($id);
        $ttl = $default;

        if (isset($this->items[$key])) {
            $ttl = $this->items[$key]['ttl'];
        }

        return $ttl;
    }

    /**
     * Save an item to cache
     *
     * @param  string $id
     * @param  mixed  $value
     * @param  ?int   $ttl
     * @return Memory
     */
    public function saveItem(string $id, mixed $value, ?int $ttl = null): Memory
    {
        $this->items[sha1($id)] = [
            'start' => $this->clock->now(),
            'ttl'   => ($ttl !== null) ? $ttl : $this->ttl,
            'value' => $value
        ];

        return $this;
    }

    /**
     * Get an item from cache
     *
     * @param  string $id
     * @param  mixed  $default
     * @return mixed
     */
    public function getItem(string $id, mixed $default = false): mixed
    {
        $key   = sha1($id);
        $value = $default;

        if (isset($this->items[$key])) {
            $cacheValue = $this->items[$key];
            if ($this->isFresh($cacheValue)) {
                $value = $cacheValue['value'];
            } else {
                $this->deleteItem($id);
            }
        }

        return $value;
    }

    /**
     * Determine if the item exist in cache
     *
     * @param  string $id
     * @return bool
     */
    public function hasItem(string $id): bool
    {
        $key    = sha1($id);
        $result = false;

        if (isset($this->items[$key])) {
            $cacheValue = $this->items[$key];
            $result     = $this->isFresh($cacheValue);
        }

        return $result;
    }

    /**
     * Delete a value in cache
     *
     * @param  string $id
     * @return Memory
     */
    public function deleteItem(string $id): Memory
    {
        unset($this->items[sha1($id)]);
        return $this;
    }

    /**
     * Clear all stored values from cache
     *
     * @return Memory
     */
    public function clear(): Memory
    {
        $this->items = [];
        return $this;
    }

    /**
     * Destroy cache resource
     *
     * @return Memory
     */
    public function destroy(): Memory
    {
        $this->clear();
        return $this;
    }

    /**
     * Atomically increment a counter in cache, creating it at $initial if it doesn't exist
     *
     * Non-atomic read-modify-write through the same start/ttl/value envelope used by saveItem()/getItem() —
     * Memory has no native atomic primitive, so a counter here is an ordinary cached integer, fully
     * interoperable with getItem()/hasItem()/deleteItem().
     *
     * @param  string $id
     * @param  int    $amount
     * @param  int    $initial
     * @param  ?int   $ttl
     * @throws Exception
     * @return int
     */
    public function incrementItem(string $id, int $amount = 1, int $initial = 0, ?int $ttl = null): int
    {
        $current = $this->getItem($id, $initial);

        if (!is_int($current)) {
            throw new Exception('Error: The value at that key is not numeric.');
        }

        $value = $current + $amount;
        $this->saveItem($id, $value, $ttl);

        return $value;
    }

    /**
     * Atomically decrement a counter in cache, creating it at $initial if it doesn't exist
     *
     * Non-atomic read-modify-write through the same start/ttl/value envelope used by saveItem()/getItem() —
     * Memory has no native atomic primitive, so a counter here is an ordinary cached integer, fully
     * interoperable with getItem()/hasItem()/deleteItem().
     *
     * @param  string $id
     * @param  int    $amount
     * @param  int    $initial
     * @param  ?int   $ttl
     * @throws Exception
     * @return int
     */
    public function decrementItem(string $id, int $amount = 1, int $initial = 0, ?int $ttl = null): int
    {
        $current = $this->getItem($id, $initial);

        if (!is_int($current)) {
            throw new Exception('Error: The value at that key is not numeric.');
        }

        $value = $current - $amount;
        $this->saveItem($id, $value, $ttl);

        return $value;
    }

}
