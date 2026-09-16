<?php
declare(strict_types=1);
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
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
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.0.0
 */
class Redis extends AbstractAdapter
{

    /**
     * Traits
     */
    use NamespacedVersionedKeys;

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
     * Connection options and their defaults
     *
     * Every default reproduces the behaviour this adapter had before these options existed, so an
     * omitted key never changes anything. Doubles as the whitelist validateOptions() checks against.
     *
     * @var array
     */
    protected const array CONNECTION_OPTIONS = [
        'timeout'        => 0.0,
        'read_timeout'   => 0.0,
        'retry_interval' => 0,
        'persistent'     => false,
        'password'       => null,
        'username'       => null,
        'database'       => null,
    ];

    /**
     * Constructor
     *
     * Instantiate the redis cache object
     *
     * $options is appended and defaults to [], so every existing call -- positional or named -- keeps
     * working unchanged. Recognised keys, matching the array-of-options convention pop-db's adapters
     * and pop-http's Client already use:
     *
     *   timeout        float  connect timeout in seconds; 0.0 means unlimited (default 0.0)
     *   read_timeout   float  read timeout in seconds; 0.0 means unlimited (default 0.0)
     *   retry_interval int    retry interval in milliseconds (default 0)
     *   persistent     bool   use pconnect(), reusing the connection across requests (default false)
     *   password       string AUTH password (default none)
     *   username       string AUTH username for Redis 6+ ACLs; requires password (default none)
     *   database       int    SELECT this database index (default: stay on the server's default)
     *
     * Both timeouts default to 0.0, which phpredis reads as "unlimited" -- the pre-existing
     * behaviour. Any caller on a request path should set both: `timeout` bounds a host that will not
     * accept a connection, while `read_timeout` bounds a host that accepts one and then never
     * answers. Those are different failures and the first does nothing about the second; a hung
     * server blocks a worker just as hard as an unreachable one.
     *
     * An unrecognised key is an error rather than a silent no-op. Getting that wrong is the whole
     * risk of an options array over named parameters: `read_timout => 1.0` would otherwise mean no
     * read timeout at all, and nothing would say so until a worker hung in production.
     *
     * @param  int    $ttl
     * @param  string $host
     * @param  int    $port
     * @param  string $namespace
     * @param  Clock\ClockInterface $clock
     * @param  array  $options
     * @throws Exception
     */
    public function __construct(
        int $ttl = 0, string $host = 'localhost', int $port = 6379, string $namespace = 'pop_cache',
        Clock\ClockInterface $clock = new Clock\SystemClock(), array $options = []
    )
    {
        parent::__construct($ttl, $clock);
        if (!class_exists('Redis', false)) {
            throw new Exception('Error: Redis is not available.');
        }

        $options = $this->validateOptions($options);

        $this->namespace = $namespace;
        $this->redis     = new \Redis();

        $this->connect($host, $port, $options);
    }

    /**
     * Validate connection options against the recognised set and merge in the defaults
     *
     * @param  array $options
     * @throws Exception
     * @return array
     */
    protected function validateOptions(array $options): array
    {
        $unknown = array_diff(array_keys($options), array_keys(static::CONNECTION_OPTIONS));

        if (!empty($unknown)) {
            throw new Exception(
                'Error: Unrecognized redis connection option(s): ' . implode(', ', $unknown) .
                '. Recognized options are: ' . implode(', ', array_keys(static::CONNECTION_OPTIONS)) . '.'
            );
        }

        return array_merge(static::CONNECTION_OPTIONS, $options);
    }

    /**
     * Open the connection, authenticate and select the database
     *
     * @param  string $host
     * @param  int    $port
     * @param  array  $options already validated and merged with the defaults
     * @throws Exception
     * @return void
     */
    protected function connect(string $host, int $port, array $options): void
    {
        // A persistent connection is keyed by its persistent_id: connections sharing an id are pooled
        // and reused, so the id folds in every option that changes what the connection *is*. Two
        // adapters pointing at different databases or authenticating as different users must not land
        // on the same pooled socket.
        $persistentId = ($options['persistent']) ?
            $this->namespace . ':' . $options['database'] . ':' . $options['username'] : null;

        // phpredis is inconsistent about how it reports failure: connect(), pconnect() and auth()
        // raise \RedisException, while select() returns false. The old `if (!$this->redis->connect(...))`
        // guard was therefore unreachable -- a refused connection has always escaped as a raw
        // \RedisException, past this package's documented @throws Exception. Normalising all of it
        // here means a caller can catch Pop\Cache\Adapter\Exception and actually get every
        // connection-time failure, with the phpredis exception preserved as $previous.
        try {
            $connected = ($options['persistent']) ?
                $this->redis->pconnect(
                    $host, $port, (float)$options['timeout'], $persistentId,
                    (int)$options['retry_interval'], (float)$options['read_timeout']
                ) :
                $this->redis->connect(
                    $host, $port, (float)$options['timeout'], null,
                    (int)$options['retry_interval'], (float)$options['read_timeout']
                );

            if (!$connected) {
                throw new Exception('Error: Unable to connect to the redis server.');
            }

            if ($options['password'] !== null) {
                $credentials = ($options['username'] !== null) ?
                    [$options['username'], $options['password']] : $options['password'];

                if (!$this->redis->auth($credentials)) {
                    throw new Exception('Error: Unable to authenticate with the redis server.');
                }
            }

            if ($options['database'] !== null) {
                if (!$this->redis->select((int)$options['database'])) {
                    throw new Exception('Error: Unable to select the redis database.');
                }
            }
        } catch (\RedisException $exception) {
            throw new Exception('Error: ' . $exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * Get the redis object.
     *
     * @throws Exception if the connection has already been destroyed
     * @return \Redis
     */
    public function redis(): \Redis
    {
        if ($this->redis === null) {
            throw new Exception('Error: The redis connection has been destroyed.');
        }

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
                if ($this->isFresh($cacheValue)) {
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
        $this->redis->set($this->versionKey(), $this->nextVersion());
        return $this;
    }

    /**
     * Destroy cache resource
     *
     * Bumps the namespace version (as clear() does) and drops the connection. The adapter is spent
     * afterwards: any further call raises Pop\Cache\Adapter\Exception rather than a TypeError or a
     * "call to a member function on null" fatal.
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
     * expiry. Use getCounter() to read one back.
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
     * Read a counter written by incrementItem()/decrementItem(), or null if it does not exist
     *
     * Counters are raw scalars, so getItem() cannot read them -- it looks for the start/ttl/value
     * envelope, finds a bare integer, and returns its $default. Before this method there was no public
     * way to read a counter at all: key() is protected, so callers could not even reach around the
     * adapter to do it themselves. The nearest workaround, incrementItem($id, 0), mutates nothing but
     * *creates* the key at $initial when it is absent, so it reports 0 for a counter that has never
     * been touched and cannot distinguish that from a counter genuinely sitting at 0. This can, and
     * returns null for the former.
     *
     * A counter that exists but holds a non-numeric value is reported as null rather than coerced to 0
     * -- incrementItem() throws on the same data, and silently reading it as zero is how a rate limiter
     * ends up permanently believing no requests have happened.
     *
     * @param  string $id
     * @return ?int
     */
    public function getCounter(string $id): ?int
    {
        $value = $this->redis->get($this->key($id));

        if (($value === false) || ($value === null) || !is_numeric($value)) {
            return null;
        }

        return (int)$value;
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
     * Fetch the raw version value from redis, or false if it isn't set
     *
     * @param  string $key
     * @return mixed
     */
    protected function fetchVersion(string $key): mixed
    {
        return $this->redis->get($key);
    }

}
