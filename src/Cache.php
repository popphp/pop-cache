<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2015 NOLA Interactive, LLC. (http://www.nolainteractive.com)
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
 * @package    Pop_Cache
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2015 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.1
 */
class Cache
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
     * @return Cache
     */
    public function __construct(Adapter\AdapterInterface $adapter)
    {
        $this->setAdapter($adapter);
    }

    /**
     * Determine available adapters
     *
     * @return array
     */
    public static function getAvailableAdapters()
    {
        $pdoDrivers = (class_exists('Pdo', false)) ? \PDO::getAvailableDrivers() : [];
        if (class_exists('Sqlite3') || in_array('sqlite', $pdoDrivers)) {
            $adapters[] = 'Sqlite';
        }

        return [
            'apc'       => (function_exists('apc_cache_info')),
            'file'      => true,
            'memcached' => (class_exists('Memcache', false)),
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
     * Set the adapter
     *
     * @param  Adapter\AdapterInterface $adapter
     * @return Cache
     */
    public function setAdapter(Adapter\AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
        return $this;
    }

    /**
     * Get the adapter
     *
     * @return mixed
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * Get the adapter (alias method)
     *
     * @return mixed
     */
    public function adapter()
    {
        return $this->adapter;
    }

    /**
     * Save a value to cache.
     *
     * @param  string $id
     * @param  mixed  $value
     * @return Cache
     */
    public function save($id, $value)
    {
        $this->adapter->save($id, $value);
        return $this;
    }

    /**
     * Load a value from cache.
     *
     * @param  string $id
     * @return mixed
     */
    public function load($id)
    {
        return $this->adapter->load($id);
    }

    /**
     * Remove a value in cache.
     *
     * @param  string $id
     * @return Cache
     */
    public function remove($id)
    {
        $this->adapter->remove($id);
        return $this;
    }

    /**
     * Clear all stored values from cache.
     *
     * @param  boolean $del
     * @return Cache
     */
    public function clear($del = false)
    {
        if (($this->adapter instanceof Adapter\File) || ($this->adapter instanceof Adapter\Sqlite)) {
            $this->adapter->clear($del);
        } else {
            $this->adapter->clear();
        }
        return $this;
    }

    /**
     * Tell is a value is expired.
     *
     * @param  string $id
     * @return boolean
     */
    public function isExpired($id)
    {
        return $this->adapter->isExpired($id);
    }

    /**
     * Get original start timestamp of the value.
     *
     * @param  string $id
     * @return int
     */
    public function getStart($id)
    {
        return $this->adapter->getStart($id);
    }

    /**
     * Get expiration timestamp of the value.
     *
     * @param  string $id
     * @return int
     */
    public function getExpiration($id)
    {
        return $this->adapter->getExpiration($id);
    }

    /**
     * Get the lifetime of the value.
     *
     * @param  string $id
     * @return int
     */
    public function getLifetime($id)
    {
        return $this->adapter->getLifetime($id);
    }

}
