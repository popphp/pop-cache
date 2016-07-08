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
 * Redis cache adapter class
 *
 * @category   Pop
 * @package    Pop_Cache
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2016 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.2.0
 */
class Redis extends AbstractAdapter
{

    /**
     * Redis object
     * @var \Redis
     */
    protected $redis = null;

    /**
     * Redis version
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
     * @return Redis
     */
    public function __construct($lifetime = 0, $host = 'localhost', $port = 6379)
    {
        parent::__construct($lifetime);
        if (!class_exists('Redis', false)) {
            throw new Exception('Error: Redis is not available.');
        }

        $this->redis = new \Redis();
        if (!$this->redis->connect($host, (int)$port)) {
            throw new Exception('Error: Unable to connect to the memcached server.');
        }

        $this->version = $this->redis->info()['redis_version'];
    }

    /**
     * Get the current version of redis.
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
     * @return Redis
     */
    public function save($id, $value)
    {
        $cacheValue = [
            'start'    => time(),
            'expire'   => ($this->lifetime != 0) ? time() + $this->lifetime : 0,
            'lifetime' => $this->lifetime,
            'value'    => $value
        ];

        if ($this->lifetime != 0) {
            $this->redis->set($id, serialize($cacheValue), $this->lifetime);
        } else {
            $this->redis->set($id, serialize($cacheValue));
        }
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
        $cacheValue = $this->redis->get($id);
        $value      = false;

        if ($cacheValue !== false) {
            $cacheValue = unserialize($cacheValue);
            $value      = $cacheValue['value'];
        }

        return $value;
    }

    /**
     * Remove a value in cache.
     *
     * @param  string $id
     * @return Redis
     */
    public function remove($id)
    {
        $this->redis->delete($id);
        return $this;
    }

    /**
     * Clear all stored values from cache.
     *
     * @return Redis
     */
    public function clear()
    {
        $this->redis->flushDb();
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
        $cacheValue = $this->redis->get($id);
        $value      = 0;

        if ($cacheValue !== false) {
            $cacheValue = unserialize($cacheValue);
            $value      = $cacheValue['start'];
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
        $cacheValue = $this->redis->get($id);
        $value      = 0;

        if ($cacheValue !== false) {
            $cacheValue = unserialize($cacheValue);
            $value      = $cacheValue['expire'];
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
        $cacheValue = $this->redis->get($id);
        $value      = 0;

        if ($cacheValue !== false) {
            $cacheValue = unserialize($cacheValue);
            $value      = $cacheValue['lifetime'];
        }

        return $value;
    }

}
