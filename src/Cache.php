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
namespace Pop\Cache;

use Pop\Cache\Adapter\AdapterInterface;

/**
 * Cache class
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    4.0.2
 */
class Cache implements \ArrayAccess
{

    /**
     * Cache adapter
     * @var ?Adapter\AdapterInterface
     */
    protected ?Adapter\AdapterInterface $adapter = null;

    /**
     * Constructor
     *
     * Instantiate the cache object
     *
     * @param  Adapter\AdapterInterface $adapter
     */
    public function __construct(Adapter\AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * Determine available adapters
     *
     * @return array
     */
    public static function getAvailableAdapters(): array
    {
        $pdoDrivers = (class_exists('Pdo', false)) ? \PDO::getAvailableDrivers() : [];

        return [
            'apc'       => (function_exists('apc_cache_info')),
            'file'      => true,
            'memcached' => (class_exists('Memcache', false)),
            'redis'     => (class_exists('Redis', false)),
            'session'   => (function_exists('session_start')),
            'sqlite'    => (class_exists('Sqlite3') || in_array('sqlite', $pdoDrivers))
        ];
    }

    /**
     * Determine if an adapter is available
     *
     * @param  string $adapter
     * @return bool
     */
    public static function isAvailable(string $adapter): bool
    {
        $adapter  = strtolower($adapter);
        $adapters = self::getAvailableAdapters();
        return (isset($adapters[$adapter]) && ($adapters[$adapter]));
    }

    /**
     * Get the adapter
     *
     * @return ?Adapter\AdapterInterface
     */
    public function adapter(): ?Adapter\AdapterInterface
    {
        return $this->adapter;
    }

    /**
     * Get global cache TTL
     *
     * @return int
     */
    public function getTtl(): int
    {
        return $this->adapter->getTtl();
    }

    /**
     * Get item cache TTL
     *
     * @param  string $id
     * @return int
     */
    public function getItemTtl(string $id): int
    {
        return $this->adapter->getItemTtl($id);
    }

    /**
     * Save an item to cache
     *
     * @param  string $id
     * @param  mixed  $value
     * @param  ?int   $ttl
     * @return void
     */
    public function saveItem(string $id, mixed $value, ?int $ttl = null): void
    {
        $this->adapter->saveItem($id, $value, $ttl);
    }

    /**
     * Save items to cache
     *
     * @param  array $items
     * @return void
     */
    public function saveItems(array $items): void
    {
        foreach ($items as $id => $value) {
            $this->adapter->saveItem($id, $value);
        }
    }

    /**
     * Get an item from cache
     *
     * @param  string $id
     * @return mixed
     */
    public function getItem(string $id): mixed
    {
        return $this->adapter->getItem($id);
    }

    /**
     * Determine if the item is in cache
     *
     * @param  string $id
     * @return bool
     */
    public function hasItem(string $id): bool
    {
        return $this->adapter->hasItem($id);
    }

    /**
     * Delete an item in cache
     *
     * @param  string $id
     * @return void
     */
    public function deleteItem(string $id): void
    {
        $this->adapter->deleteItem($id);
    }

    /**
     * Delete items in cache
     *
     * @param  array $ids
     * @return void
     */
    public function deleteItems(array $ids): void
    {
        foreach ($ids as $id) {
            $this->adapter->deleteItem($id);
        }
    }

    /**
     * Clear all stored values from cache
     *
     * @return void
     */
    public function clear(): void
    {
        $this->adapter->clear();
    }

    /**
     * Destroy cache resource
     *
     * @return void
     */
    public function destroy(): void
    {
        $this->adapter->destroy();
    }

    /**
     * Magic get method to return an item from cache
     *
     * @param  string $name
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return $this->adapter->getItem($name);
    }

    /**
     * Magic set method to save an item in the cache
     *
     * @param  string $name
     * @param  mixed $value
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        $this->adapter->saveItem($name, $value);
    }

    /**
     * Determine if the item is in cache
     *
     * @param  string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return $this->adapter->hasItem($name);
    }

    /**
     * Delete value from cache
     *
     * @param  string $name
     * @return void
     */
    public function __unset(string $name): void
    {
        $this->adapter->deleteItem($name);
    }

    /**
     * ArrayAccess offsetExists
     *
     * @param  mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->__isset($offset);
    }

    /**
     * ArrayAccess offsetGet
     *
     * @param  mixed $offset
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->__get($offset);
    }

    /**
     * ArrayAccess offsetSet
     *
     * @param  mixed $offset
     * @param  mixed $value
     * @return void
     */
    public function offsetSet(mixed $offset, mixed  $value): void
    {
        $this->__set($offset, $value);
    }

    /**
     * ArrayAccess offsetUnset
     *
     * @param  mixed $offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->__unset($offset);
    }

}
