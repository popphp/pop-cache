<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2016 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Cache\Adapter;

/**
 * Memcached cache adapter class
 *
 * @category   Pop
 * @package    Pop_Cache
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2016 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.1
 */
class Memcached extends AbstractAdapter
{

    /**
     * Memcache object
     * @var \Memcache
     */
    protected $memcache = null;

    /**
     * Memcache version
     * @var string
     */
    protected $version = null;

    /**
     * Constructor
     *
     * Instantiate the memcache cache object
     *
     * @param  int    $lifetime
     * @param  string $host
     * @param  int    $port
     * @throws Exception
     * @return Memcached
     */
    public function __construct($lifetime = 0, $host = 'localhost', $port = 11211)
    {
        parent::__construct($lifetime);
        if (!class_exists('Memcache', false)) {
            throw new Exception('Error: Memcache is not available.');
        }

        $this->memcache = new \Memcache();
        if (!$this->memcache->connect($host, (int)$port)) {
            throw new Exception('Error: Unable to connect to the memcached server.');
        }

        $this->version = $this->memcache->getVersion();
    }

    /**
     * Get the current version of memcache.
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Save a value to cache.
     *
     * @param  string $id
     * @param  mixed  $value
     * @return Memcached
     */
    public function save($id, $value)
    {
        $cacheValue = [
            'start'    => time(),
            'expire'   => ($this->lifetime != 0) ? time() + $this->lifetime : 0,
            'lifetime' => $this->lifetime,
            'value'    => $value
        ];

        $this->memcache->set($id, $cacheValue, false, $this->lifetime);
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
        $cacheValue = $this->memcache->get($id);
        $value      = false;

        if ($cacheValue !== false) {
            $value = $cacheValue['value'];
        }

        return $value;
    }

    /**
     * Remove a value in cache.
     *
     * @param  string $id
     * @return Memcached
     */
    public function remove($id)
    {
        $this->memcache->delete($id);
        return $this;
    }

    /**
     * Clear all stored values from cache.
     *
     * @return Memcached
     */
    public function clear()
    {
        $this->memcache->flush();
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
        return ($this->load($id) === false);
    }

    /**
     * Get original start timestamp of the value.
     *
     * @param  string $id
     * @return int
     */
    public function getStart($id)
    {
        $cacheValue = $this->memcache->get($id);
        $value      = 0;

        if ($cacheValue !== false) {
            $value = $cacheValue['start'];
        }

        return $value;
    }

    /**
     * Get expiration timestamp of the value.
     *
     * @param  string $id
     * @return int
     */
    public function getExpiration($id)
    {
        $cacheValue = $this->memcache->get($id);
        $value      = 0;

        if ($cacheValue !== false) {
            $value = $cacheValue['expire'];
        }

        return $value;
    }

    /**
     * Get the lifetime of the value.
     *
     * @param  string $id
     * @return int
     */
    public function getLifetime($id)
    {
        $cacheValue = $this->memcache->get($id);
        $value      = 0;

        if ($cacheValue !== false) {
            $value = $cacheValue['lifetime'];
        }

        return $value;
    }

}
