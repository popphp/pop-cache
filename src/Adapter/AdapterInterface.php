<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Cache\Adapter;

/**
 * Cache adapter interface
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    4.0.0
 */
interface AdapterInterface
{

    /**
     * Set the global time-to-live for the cache adapter
     *
     * @param  int $ttl
     * @return AdapterInterface
     */
    public function setTtl(int $ttl): AdapterInterface;

    /**
     * Get the global time-to-live for the cache object
     *
     * @return int
     */
    public function getTtl(): int;

    /**
     * Get the time-to-live for an item in cache
     *
     * @param  string $id
     * @return int
     */
    public function getItemTtl(string $id): int;

    /**
     * Save an item to cache
     *
     * @param  string $id
     * @param  mixed  $value
     * @param  ?int   $ttl
     * @return AdapterInterface
     */
    public function saveItem(string $id, mixed $value, ?int $ttl = null): AdapterInterface;

    /**
     * Get an item from cache
     *
     * @param  string $id
     * @return mixed
     */
    public function getItem(string $id): mixed;

    /**
     * Determine if the item exist in cache
     *
     * @param  string $id
     * @return bool
     */
    public function hasItem(string $id): bool;

    /**
     * Delete a value in cache
     *
     * @param  string $id
     * @return AdapterInterface
     */
    public function deleteItem(string $id): AdapterInterface;

    /**
     * Clear all stored values from cache
     *
     * @return AdapterInterface
     */
    public function clear(): AdapterInterface;

    /**
     * Destroy cache resource
     *
     * @return AdapterInterface
     */
    public function destroy(): AdapterInterface;

}
