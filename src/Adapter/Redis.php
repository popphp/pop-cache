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

use Pop\Cache\Clock;

/**
 * Redis cache adapter class
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.0.0
 */
class Redis extends AbstractAdapter
{

    /**
     * Redis object
     * @var ?\Redis
     */
    protected ?\Redis $redis = null;

    /**
     * Cache namespace
     * @var string
     */
    protected string $namespace = 'pop_cache';

    /**
     * Constructor
     *
     * Instantiate the memcache cache object
     *
     * @param  int    $ttl
     * @param  string $host
     * @param  int    $port
     * @param  string $namespace
     * @param  Clock\ClockInterface $clock
     * @throws Exception
     */
    public function __construct(
        int $ttl = 0, string $host = 'localhost', int $port = 6379, string $namespace = 'pop_cache',
        Clock\ClockInterface $clock = new Clock\SystemClock()
    )
    {
        parent::__construct($ttl, $clock);
        if (!class_exists('Redis', false)) {
            throw new Exception('Error: Redis is not available.');
        }

        $this->namespace = $namespace;
        $this->redis     = new \Redis();
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
     * @param  int    $default
     * @return int
     */
    public function getItemTtl(string $id, int $default = 0): int
    {
        $cacheValue = $this->redis->get($this->key($id));
        $ttl        = $default;

        if (is_string($cacheValue) && str_starts_with($cacheValue, 'a:')) {
            $cacheValue = unserialize($cacheValue, ['allowed_classes' => false]);
            if (is_array($cacheValue) && array_key_exists('ttl', $cacheValue)) {
                $ttl = $cacheValue['ttl'];
            }
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
            'start' => $this->clock->now(),
            'ttl'   => ($ttl !== null) ? $ttl : $this->ttl,
            'value' => $value
        ];

        if ($cacheValue['ttl'] != 0) {
            $this->redis->set($this->key($id), serialize($cacheValue), $cacheValue['ttl']);
        } else {
            $this->redis->set($this->key($id), serialize($cacheValue));
        }
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
        $cacheValue = $this->redis->get($this->key($id));
        $value      = $default;

        if (is_string($cacheValue) && str_starts_with($cacheValue, 'a:')) {
            $cacheValue = unserialize($cacheValue, ['allowed_classes' => false]);
            if (is_array($cacheValue) && array_key_exists('start', $cacheValue) &&
                array_key_exists('ttl', $cacheValue) && array_key_exists('value', $cacheValue)) {
                if (($cacheValue['ttl'] == 0) || (($this->clock->now() - $cacheValue['start']) <= $cacheValue['ttl'])) {
                    $value = $cacheValue['value'];
                } else {
                    $this->deleteItem($id);
                }
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
        $this->redis->del($this->key($id));
        return $this;
    }

    /**
     * Clear all stored values from cache
     *
     * @return Redis
     */
    public function clear(): Redis
    {
        $this->redis->set($this->versionKey(), $this->resolveVersion() + 1);
        return $this;
    }

    /**
     * Destroy cache resource
     *
     * @return Redis
     */
    public function destroy(): Redis
    {
        $this->clear();
        $this->redis = null;
        return $this;
    }

    /**
     * Lua script for incrementItem()/decrementItem(): atomically seeds a new counter at the given initial
     * value (with a TTL, if any) when the key doesn't exist yet, then applies the delta. Redis's
     * single-threaded script execution guarantees the whole sequence is atomic. decrementItem() reuses this
     * same script by passing a negative amount — INCRBY with a negative delta is exactly DECRBY.
     * @var string
     */
    protected const string INCREMENT_SCRIPT = <<<'LUA'
        local key = KEYS[1]
        local amount = tonumber(ARGV[1])
        local initial = tonumber(ARGV[2])
        local ttl = tonumber(ARGV[3])
        if redis.call('EXISTS', key) == 0 then
            if ttl > 0 then
                redis.call('SET', key, initial, 'EX', ttl)
            else
                redis.call('SET', key, initial)
            end
        end
        return redis.call('INCRBY', key, amount)
        LUA;

    /**
     * Atomically increment a counter in cache, creating it at $initial if it doesn't exist
     *
     * Stored as a raw scalar via a Lua script (see INCREMENT_SCRIPT), bypassing the start/ttl/value
     * envelope used by saveItem()/getItem() entirely — a counter key and a saveItem()-managed key are two
     * incompatible storage formats on this adapter, and a counter is not readable via getItem(). $ttl is
     * honored only when the counter is first created; a later call does not refresh an existing counter's
     * expiry.
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
        return $this->evalIncrement($id, $amount, $initial, $ttl);
    }

    /**
     * Atomically decrement a counter in cache, creating it at $initial if it doesn't exist
     *
     * Stored as a raw scalar via a Lua script (see INCREMENT_SCRIPT), bypassing the start/ttl/value
     * envelope used by saveItem()/getItem() entirely — a counter key and a saveItem()-managed key are two
     * incompatible storage formats on this adapter, and a counter is not readable via getItem(). Unlike
     * Memcached::decrement(), Redis allows the result to go negative (no clamping).
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
        return $this->evalIncrement($id, -$amount, $initial, $ttl);
    }

    /**
     * Shared implementation for incrementItem()/decrementItem(), executing INCREMENT_SCRIPT atomically
     *
     * @param  string $id
     * @param  int    $amount
     * @param  int    $initial
     * @param  ?int   $ttl
     * @throws Exception
     * @return int
     */
    protected function evalIncrement(string $id, int $amount, int $initial, ?int $ttl): int
    {
        $key = $this->key($id);
        $ttl = ($ttl !== null) ? $ttl : $this->ttl;

        $this->redis->clearLastError();
        $result = $this->redis->eval(self::INCREMENT_SCRIPT, [$key, $amount, $initial, $ttl], 1);

        if ($result === false) {
            throw new Exception('Error: The value at that key is not numeric.');
        }

        return $result;
    }

    /**
     * Get the storage key for this namespace's version counter
     *
     * @return string
     */
    protected function versionKey(): string
    {
        return $this->namespace . '::version';
    }

    /**
     * Resolve the current version for this namespace, defaulting to 1
     *
     * @return int
     */
    protected function resolveVersion(): int
    {
        $version = $this->redis->get($this->versionKey());
        return ($version !== false) ? (int)$version : 1;
    }

    /**
     * Build the versioned, namespaced storage key for an item id
     *
     * @param  string $id
     * @return string
     */
    protected function key(string $id): string
    {
        return $this->namespace . ':v' . $this->resolveVersion() . ':' . sha1($id);
    }

}
