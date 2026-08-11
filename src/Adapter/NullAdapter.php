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

/**
 * Null adapter cache class
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.0.0
 */
class NullAdapter extends AbstractAdapter
{

    /**
     * Get the time-to-live for an item in cache
     *
     * @param  string $id
     * @param  int    $default
     * @return int
     */
    public function getItemTtl(string $id, int $default = 0): int
    {
        return $default;
    }

    /**
     * Save an item to cache
     *
     * @param  string $id
     * @param  mixed  $value
     * @param  ?int   $ttl
     * @return NullAdapter
     */
    public function saveItem(string $id, mixed $value, ?int $ttl = null): NullAdapter
    {
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
        return $default;
    }

    /**
     * Determine if the item exist in cache
     *
     * @param  string $id
     * @return bool
     */
    public function hasItem(string $id): bool
    {
        return false;
    }

    /**
     * Delete a value in cache
     *
     * @param  string $id
     * @return NullAdapter
     */
    public function deleteItem(string $id): NullAdapter
    {
        return $this;
    }

    /**
     * Clear all stored values from cache
     *
     * @return NullAdapter
     */
    public function clear(): NullAdapter
    {
        return $this;
    }

    /**
     * Destroy cache resource
     *
     * @return NullAdapter
     */
    public function destroy(): NullAdapter
    {
        return $this;
    }

    /**
     * Always returns $initial + $amount — nothing is ever actually persisted, so every call behaves as if
     * the counter had just been created and immediately incremented once; there is no "first call" state.
     *
     * @param  string $id
     * @param  int    $amount
     * @param  int    $initial
     * @param  ?int   $ttl
     * @return int
     */
    public function incrementItem(string $id, int $amount = 1, int $initial = 0, ?int $ttl = null): int
    {
        return $initial + $amount;
    }

    /**
     * Always returns $initial - $amount — nothing is ever actually persisted, so every call behaves as if
     * the counter had just been created and immediately decremented once; there is no "first call" state.
     *
     * @param  string $id
     * @param  int    $amount
     * @param  int    $initial
     * @param  ?int   $ttl
     * @return int
     */
    public function decrementItem(string $id, int $amount = 1, int $initial = 0, ?int $ttl = null): int
    {
        return $initial - $amount;
    }

}
