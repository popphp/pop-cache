<?php
declare(strict_types=1);
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
 * Memcached cache adapter class
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.0.0
 */
class Memcached extends AbstractAdapter
{

    /**
     * Memcached object
     * @var ?\Memcached
     */
    protected ?\Memcached $memcached = null;

    /**
     * Memcached version
     * @var ?string
     */
    protected ?string $version = null;

    /**
     * Cache namespace
     * @var string
     */
    protected string $namespace = 'pop_cache';

    /**
     * Constructor
     *
     * Instantiate the memcached cache object
     *
     * @param  int    $ttl
     * @param  string $host
     * @param  int    $port
     * @param  int    $weight
     * @param  string $namespace
     * @param  Clock\ClockInterface $clock
     * @throws Exception
     */
    public function __construct(
        int $ttl = 0, string $host = 'localhost', int $port = 11211, int $weight = 1,
        string $namespace = 'pop_cache', Clock\ClockInterface $clock = new Clock\SystemClock()
    )
    {
        parent::__construct($ttl, $clock);
        if (!class_exists('Memcached', false)) {
            throw new Exception('Error: Memcached is not available.');
        }

        $this->namespace = $namespace;
        $this->memcached = new \Memcached();
        $this->memcached->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
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
    public function memcached(): \Memcached
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
    public function addServer(string $host, int $port = 11211, int $weight = 1): Memcached
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
    public function addServers(array $servers): Memcached
    {
        $this->memcached->addServers($servers);
        return $this;
    }

    /**
     * Get the current version of memcached.
     *
     * @return ?string
     */
    public function getVersion(): ?string
    {
        return $this->version;
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
        $cacheValue = $this->memcached->get($this->key($id));
        $ttl        = $default;

        if (is_array($cacheValue) && array_key_exists('ttl', $cacheValue)) {
            $ttl = $cacheValue['ttl'];
        }

        return $ttl;
    }

    /**
     * Save an item to cache
     *
     * @param  string $id
     * @param  mixed  $value
     * @param  ?int   $ttl
     * @return Memcached
     */
    public function saveItem(string $id, mixed $value, ?int $ttl = null): Memcached
    {
        $cacheValue = [
            'start' => $this->clock->now(),
            'ttl'   => ($ttl !== null) ? $ttl : $this->ttl,
            'value' => $value
        ];

        $this->memcached->set($this->key($id), $cacheValue, $cacheValue['ttl']);

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
        $cacheValue = $this->memcached->get($this->key($id));
        $value      = $default;

        if (is_array($cacheValue) && array_key_exists('start', $cacheValue) &&
            array_key_exists('ttl', $cacheValue) && array_key_exists('value', $cacheValue)) {
            if (($cacheValue['ttl'] == 0) || (($this->clock->now() - $cacheValue['start']) <= $cacheValue['ttl'])) {
                $value = $cacheValue['value'];
            } else {
                $this->deleteItem($id);
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
        return ($this->getItem($id) !== false);
    }

    /**
     * Delete a value in cache
     *
     * @param  string $id
     * @return Memcached
     */
    public function deleteItem(string $id): Memcached
    {
        $this->memcached->delete($this->key($id));
        return $this;
    }

    /**
     * Clear all stored values from cache
     *
     * @return Memcached
     */
    public function clear(): Memcached
    {
        $this->memcached->set($this->versionKey(), $this->resolveVersion() + 1, 0);
        return $this;
    }

    /**
     * Destroy cache resource
     *
     * @return Memcached
     */
    public function destroy(): Memcached
    {
        $this->clear();
        $this->memcached = null;
        return $this;
    }

    /**
     * Atomically increment a counter in cache, creating it at $initial if it doesn't exist
     *
     * Stored as a raw scalar, bypassing the start/ttl/value envelope used by saveItem()/getItem() entirely
     * — a counter key and a saveItem()-managed key are two incompatible storage formats on this adapter,
     * and a counter is not readable via getItem(). Requires the binary protocol (enabled in the
     * constructor) — the default ASCII protocol silently ignores a non-default initial value. $ttl is
     * honored only when the counter is first created; a later call does not refresh an existing counter's
     * expiry.
     *
     * Deviation from the original plan: Memcached::increment() alone, when creating a new counter, seeds
     * it at $initial only and silently discards $amount (verified empirically: incrementItem('x', 5, 100)
     * on a fresh key yields 100, not 105) — unlike Apc/Redis, where the equivalent single primitive folds
     * both together. It also leaves the value stored as a raw ASCII wire-protocol counter, which
     * Memcached::get() then returns as a string, not an int. So, mirroring the Apc adapter's
     * apcu_add()+apcu_inc() pattern, this seeds via Memcached::add() (a no-op if the key already exists)
     * and then unconditionally applies Memcached::increment() on top — this also happens to make
     * Memcached::get() return a proper int thereafter, since add() stores through the normal serializer.
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
        $key = $this->key($id);
        $ttl = ($ttl !== null) ? $ttl : $this->ttl;

        $this->memcached->add($key, $initial, $ttl);
        $result = $this->memcached->increment($key, $amount, $initial, $ttl);

        if ($result === false) {
            throw new Exception('Error: The value at that key is not numeric.');
        }

        return $result;
    }

    /**
     * Atomically decrement a counter in cache, creating it at $initial if it doesn't exist
     *
     * Stored as a raw scalar, bypassing the start/ttl/value envelope used by saveItem()/getItem() entirely
     * — a counter key and a saveItem()-managed key are two incompatible storage formats on this adapter,
     * and a counter is not readable via getItem(). Unlike Redis/Apc, Memcached::decrement() clamps its
     * result at 0 (unsigned 64-bit wire protocol) rather than going negative — a documented, accepted
     * quirk of this adapter specifically.
     *
     * Deviation from the original plan: seeds via Memcached::add() before Memcached::decrement(), for the
     * same reasons as incrementItem() above (see that method's docblock) — symmetry, and so a
     * newly-created counter reflects $amount applied on top of $initial rather than $initial alone.
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
        $key = $this->key($id);
        $ttl = ($ttl !== null) ? $ttl : $this->ttl;

        $this->memcached->add($key, $initial, $ttl);
        $result = $this->memcached->decrement($key, $amount, $initial, $ttl);

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
        $version = $this->memcached->get($this->versionKey());
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
