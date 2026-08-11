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
 * APC cache adapter class
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.0.0
 */
class Apc extends AbstractAdapter
{

    /**
     * Cache namespace
     * @var string
     */
    protected string $namespace = 'pop_cache';

    /**
     * Constructor
     *
     * Instantiate the APC cache object
     *
     * @param  int $ttl
     * @param  string $namespace
     * @param  Clock\ClockInterface $clock
     * @throws Exception
     */
    public function __construct(
        int $ttl = 0, string $namespace = 'pop_cache', Clock\ClockInterface $clock = new Clock\SystemClock()
    )
    {
        parent::__construct($ttl, $clock);
        if (!function_exists('apcu_cache_info')) {
            throw new Exception('Error: APCu is not available.');
        }
        $this->namespace = $namespace;
    }

    /**
     * Method to get the current APC info.
     *
     * @return array
     */
    public function getInfo(): array
    {
        return apcu_cache_info();
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
        $cacheValue = apcu_fetch($this->key($id));
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
     * @return Apc
     */
    public function saveItem(string $id, mixed $value, ?int $ttl = null): Apc
    {
        $cacheValue = [
            'start' => $this->clock->now(),
            'ttl'   => ($ttl !== null) ? $ttl : $this->ttl,
            'value' => $value
        ];

        apcu_store($this->key($id), $cacheValue, $cacheValue['ttl']);

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
        $cacheValue = apcu_fetch($this->key($id));
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
     * @return Apc
     */
    public function deleteItem(string $id): Apc
    {
        apcu_delete($this->key($id));
        return $this;
    }

    /**
     * Clear all stored values from cache
     *
     * @return Apc
     */
    public function clear(): Apc
    {
        apcu_store($this->versionKey(), $this->resolveVersion() + 1, 0);
        return $this;
    }

    /**
     * Destroy cache resource
     *
     * @return Apc
     */
    public function destroy(): Apc
    {
        $this->clear();
        return $this;
    }

    /**
     * Atomically increment a counter in cache, creating it at $initial if it doesn't exist
     *
     * Stored as a raw scalar via apcu_add()/apcu_inc(), bypassing the start/ttl/value envelope used by
     * saveItem()/getItem() entirely — a counter key and a saveItem()-managed key are two incompatible
     * storage formats on this adapter, and a counter is not readable via getItem(). apcu_inc() has no
     * initial-value parameter of its own, so apcu_add() atomically seeds the key at $initial (only the
     * caller that finds the key missing succeeds) before apcu_inc() unconditionally applies $amount — this
     * two-call sequence stays race-free under concurrency. $ttl is honored only when the counter is first
     * created by apcu_add(); a later call does not refresh an existing counter's expiry.
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

        apcu_add($key, $initial, $ttl);

        $success = null;
        $result  = apcu_inc($key, $amount, $success, $ttl);

        if ($success === false) {
            throw new Exception('Error: The value at that key is not numeric.');
        }

        return $result;
    }

    /**
     * Atomically decrement a counter in cache, creating it at $initial if it doesn't exist
     *
     * Stored as a raw scalar via apcu_add()/apcu_dec(), bypassing the start/ttl/value envelope used by
     * saveItem()/getItem() entirely — a counter key and a saveItem()-managed key are two incompatible
     * storage formats on this adapter, and a counter is not readable via getItem(). See incrementItem() for
     * the apcu_add()+apcu_dec() atomicity rationale. Unlike Memcached::decrement(), APCu allows the result
     * to go negative.
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

        apcu_add($key, $initial, $ttl);

        $success = null;
        $result  = apcu_dec($key, $amount, $success, $ttl);

        if ($success === false) {
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
        $version = apcu_fetch($this->versionKey());
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
