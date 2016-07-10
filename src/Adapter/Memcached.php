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
 * @version    3.0.0
 */
class Memcached extends AbstractAdapter
{

    /**
     * Memcached object
     * @var \Memcached
     */
    protected $memcached = null;

    /**
     * Memcached version
     * @var string
     */
    protected $version = null;

    /**
     * Constructor
     *
     * Instantiate the memcached cache object
     *
     * @param  int    $lifetime
     * @param  string $host
     * @param  int    $port
     * @param  int    $weight
     * @throws Exception
     * @return Memcached
     */
    public function __construct($lifetime = 0, $host = 'localhost', $port = 11211, $weight = 1)
    {
        parent::__construct($lifetime);
        if (!class_exists('Memcached', false)) {
            throw new Exception('Error: Memcached is not available.');
        }

        $this->memcached = new \Memcached();
        $this->addServer($host, $port, $weight);

        $version = $this->memcached->getVersion();
        if (isset($version[$host . ':' . $port])) {
            $this->version = $version[$host . ':' . $port];
        }
    }

    /**
     * Get the memcached object.
     *
     * @return \Memcached
     */
    public function memcached()
    {
        return $this->memcached;
    }

    /**
     * Get the current version of memcached.
     *
     * @param  string $host
     * @param  int    $port
     * @param  int    $weight
     * @return Memcached
     */
    public function addServer($host, $port = 11211, $weight = 1)
    {
        $this->memcached->addServer($host, $port, $weight);
        return $this;
    }

    /**
     * Get the current version of memcached.
     *
     * @param  array $servers
     * @return Memcached
     */
    public function addServers(array $servers)
    {
        $this->memcached->addServers($servers);
        return $this;
    }

    /**
     * Get the current version of memcached.
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

        $this->memcached->add($id, $cacheValue, $this->lifetime);
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
        $cacheValue = $this->memcached->get($id);
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
        $this->memcached->delete($id);
        return $this;
    }

    /**
     * Clear all stored values from cache.
     *
     * @return Memcached
     */
    public function clear()
    {
        $this->memcached->flush();
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
        $cacheValue = $this->memcached->get($id);
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
        $cacheValue = $this->memcached->get($id);
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
        $cacheValue = $this->memcached->get($id);
        $value      = 0;

        if ($cacheValue !== false) {
            $value = $cacheValue['lifetime'];
        }

        return $value;
    }

}
