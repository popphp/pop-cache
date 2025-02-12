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
 * Redis cache adapter class
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    4.0.2
 */
class Redis extends AbstractAdapter
{

    /**
     * Redis object
     * @var ?\Redis
     */
    protected ?\Redis $redis = null;

    /**
     * Constructor
     *
     * Instantiate the memcache cache object
     *
     * @param  int    $ttl
     * @param  string $host
     * @param  int    $port
     * @throws Exception
     */
    public function __construct(int $ttl = 0, string $host = 'localhost', int $port = 6379)
    {
        parent::__construct($ttl);
        if (!class_exists('Redis', false)) {
            throw new Exception('Error: Redis is not available.');
        }

        $this->redis = new \Redis();
        if (!$this->redis->connect($host, (int)$port)) {
            throw new Exception('Error: Unable to connect to the redis server.');
        }
    }

    /**
     * Get the redis object.
     *
     * @return \Redis
     */
    public function redis(): \Redis
    {
        return $this->redis;
    }

    /**
     * Get the current version of redis.
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->redis->info()['redis_version'];
    }

    /**
     * Get the time-to-live for an item in cache
     *
     * @param  string $id
     * @return int
     */
    public function getItemTtl(string $id): int
    {
        $cacheValue = $this->redis->get($id);
        $ttl        = false;

        if ($cacheValue !== false) {
            $cacheValue = unserialize($cacheValue);
            $ttl      = $cacheValue['ttl'];
        }

        return $ttl;
    }

    /**
     * Save an item to cache
     *
     * @param  string $id
     * @param  mixed  $value
     * @param  ?int   $ttl
     * @return Redis
     */
    public function saveItem(string $id, mixed $value, ?int $ttl = null): Redis
    {
        $cacheValue = [
            'start' => time(),
            'ttl'   => ($ttl !== null) ? $ttl : $this->ttl,
            'value' => $value
        ];

        if ($cacheValue['ttl'] != 0) {
            $this->redis->set($id, serialize($cacheValue), $cacheValue['ttl']);
        } else {
            $this->redis->set($id, serialize($cacheValue));
        }
        return $this;
    }

    /**
     * Get an item from cache
     *
     * @param  string $id
     * @return mixed
     */
    public function getItem(string $id): mixed
    {
        $cacheValue = $this->redis->get($id);
        $value      = false;

        if ($cacheValue !== false) {
            $cacheValue = unserialize($cacheValue);
            if ((($cacheValue['ttl'] == 0) || ((time() - $cacheValue['start']) <= $cacheValue['ttl']))) {
                $value = $cacheValue['value'];
            } else {
                $this->deleteItem($id);
            }
        } else {
            $this->deleteItem($id);
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
        $cacheValue = $this->getItem($id);
        return ($cacheValue !== false);
    }

    /**
     * Delete a value in cache
     *
     * @param  string $id
     * @return Redis
     */
    public function deleteItem(string $id): Redis
    {
        $this->redis->del($id);
        return $this;
    }

    /**
     * Clear all stored values from cache
     *
     * @return Redis
     */
    public function clear(): Redis
    {
        $this->redis->flushDb();
        return $this;
    }

    /**
     * Destroy cache resource
     *
     * @return Redis
     */
    public function destroy(): Redis
    {
        $this->redis->flushDb();
        $this->redis = null;
        return $this;
    }

}
