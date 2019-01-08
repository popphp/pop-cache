<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
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
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.3
 */
class Cache implements \ArrayAccess
{

    /**
     * Cache adapter
     * @var AdapterInterface
     */
    protected $adapter = null;

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
    public static function getAvailableAdapters()
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
     * @return boolean
     */
    public static function isAvailable($adapter)
    {
        $adapter  = strtolower($adapter);
        $adapters = self::getAvailableAdapters();
        return (isset($adapters[$adapter]) && ($adapters[$adapter]));
    }

    /**
     * Get the adapter
     *
     * @return mixed
     */
    public function adapter()
    {
        return $this->adapter;
    }

    /**
     * Get global cache TTL
     *
     * @return int
     */
    public function getTtl()
    {
        return $this->adapter->getTtl();
    }

    /**
     * Get item cache TTL
     *
     * @param  string $id
     * @return int
     */
    public function getItemTtl($id)
    {
        return $this->adapter->getItemTtl($id);
    }

    /**
     * Save an item to cache
     *
     * @param  string $id
     * @param  mixed  $value
     * @param  int    $ttl
     * @return Cache
     */
    public function saveItem($id, $value, $ttl = null)
    {
        $this->adapter->saveItem($id, $value, $ttl);
        return $this;
    }

    /**
     * Save items to cache
     *
     * @param  array $items
     * @return Cache
     */
    public function saveItems(array $items)
    {
        foreach ($items as $id => $value) {
            $this->adapter->saveItem($id, $value);
        }
        return $this;
    }

    /**
     * Get an item from cache
     *
     * @param  string $id
     * @return mixed
     */
    public function getItem($id)
    {
        return $this->adapter->getItem($id);
    }

    /**
     * Determine if the item is in cache
     *
     * @param  string $id
     * @return mixed
     */
    public function hasItem($id)
    {
        return $this->adapter->hasItem($id);
    }

    /**
     * Delete an item in cache
     *
     * @param  string $id
     * @return Cache
     */
    public function deleteItem($id)
    {
        $this->adapter->deleteItem($id);
        return $this;
    }

    /**
     * Delete items in cache
     *
     * @param  array $ids
     * @return Cache
     */
    public function deleteItems(array $ids)
    {
        foreach ($ids as $id) {
            $this->adapter->deleteItem($id);
        }
        return $this;
    }

    /**
     * Clear all stored values from cache
     *
     * @return Cache
     */
    public function clear()
    {
        $this->adapter->clear();
        return $this;
    }

    /**
     * Destroy cache resource
     *
     * @return Cache
     */
    public function destroy()
    {
        $this->adapter->destroy();
        return $this;
    }

    /**
     * Magic get method to return an item from cache
     *
     * @param  string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->adapter->getItem($name);
    }

    /**
     * Magic set method to save an item in the cache
     *
     * @param  string $name
     * @param  mixed $value
     * @throws Exception
     * @return void
     */
    public function __set($name, $value)
    {
        $this->adapter->saveItem($name, $value);
    }

    /**
     * Determine if the item is in cache
     *
     * @param  string $name
     * @return boolean
     */
    public function __isset($name)
    {
        return $this->adapter->hasItem($name);
    }

    /**
     * Delete value from cache
     *
     * @param  string $name
     * @throws Exception
     * @return void
     */
    public function __unset($name)
    {
        $this->adapter->deleteItem($name);
    }

    /**
     * ArrayAccess offsetExists
     *
     * @param  mixed $offset
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return $this->__isset($offset);
    }

    /**
     * ArrayAccess offsetGet
     *
     * @param  mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
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
    public function offsetSet($offset, $value)
    {
        $this->__set($offset, $value);
    }

    /**
     * ArrayAccess offsetUnset
     *
     * @param  mixed $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        $this->__unset($offset);
    }

}
