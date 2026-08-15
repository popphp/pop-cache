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
 * Session adapter cache class
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.0.0
 */
class Session extends AbstractAdapter
{

    /**
     * Cache namespace
     * @var string
     */
    protected string $namespace = 'pop_cache';

    /**
     * Constructor
     *
     * Instantiate the cache session object
     *
     * @param  int $ttl
     * @param  string $namespace
     * @param  Clock\ClockInterface $clock
     */
    public function __construct(
        int $ttl = 0, string $namespace = 'pop_cache', Clock\ClockInterface $clock = new Clock\SystemClock()
    )
    {
        parent::__construct($ttl, $clock);
        $this->namespace = $namespace;
        if (session_id() == '') {
            session_start();
        }
        if (!isset($_SESSION['_POP_CACHE_'])) {
            $_SESSION['_POP_CACHE_'] = [];
        }
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
        $ttl = $default;
        $key = $this->key($id);

        if (isset($_SESSION['_POP_CACHE_'][$key])) {
            $cacheValue = unserialize($_SESSION['_POP_CACHE_'][$key], ['allowed_classes' => false]);
            $ttl        = $cacheValue['ttl'];
        }

        return $ttl;
    }

    /**
     * Save an item to cache
     *
     * @param  string $id
     * @param  mixed  $value
     * @param  ?int   $ttl
     * @return Session
     */
    public function saveItem(string $id, mixed $value, ?int $ttl = null): Session
    {
        $_SESSION['_POP_CACHE_'][$this->key($id)] = serialize([
            'start' => $this->clock->now(),
            'ttl'   => ($ttl !== null) ? $ttl : $this->ttl,
            'value' => $value
        ]);
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
        $value = $default;
        $key   = $this->key($id);

        if (isset($_SESSION['_POP_CACHE_'][$key])) {
            $cacheValue = unserialize($_SESSION['_POP_CACHE_'][$key], ['allowed_classes' => false]);
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
        $result = false;
        $key    = $this->key($id);

        if (isset($_SESSION['_POP_CACHE_'][$key])) {
            $cacheValue = unserialize($_SESSION['_POP_CACHE_'][$key], ['allowed_classes' => false]);
            $result = (($cacheValue['ttl'] == 0) || (($this->clock->now() - $cacheValue['start']) <= $cacheValue['ttl']));
        }

        return $result;
    }

    /**
     * Delete a value in cache
     *
     * @param  string $id
     * @return Session
     */
    public function deleteItem(string $id): Session
    {
        $key = $this->key($id);
        if (isset($_SESSION['_POP_CACHE_'][$key])) {
            unset($_SESSION['_POP_CACHE_'][$key]);
        }
        return $this;
    }

    /**
     * Clear all stored values from cache
     *
     * @return Session
     */
    public function clear(): Session
    {
        $prefix = $this->namespace . ':';
        foreach (array_keys($_SESSION['_POP_CACHE_']) as $k) {
            if (str_starts_with((string)$k, $prefix)) {
                unset($_SESSION['_POP_CACHE_'][$k]);
            }
        }
        return $this;
    }

    /**
     * Destroy cache resource
     *
     * @return Session
     */
    public function destroy(): Session
    {
        $_SESSION = null;
        session_unset();
        session_destroy();
        return $this;
    }

    /**
     * Atomically increment a counter in cache, creating it at $initial if it doesn't exist
     *
     * Non-atomic read-modify-write through the same start/ttl/value envelope used by saveItem()/getItem() —
     * Session has no native atomic primitive, so a counter here is an ordinary cached integer, fully
     * interoperable with getItem()/hasItem()/deleteItem().
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
        $current = $this->getItem($id, $initial);

        if (!is_int($current)) {
            throw new Exception('Error: The value at that key is not numeric.');
        }

        $value = $current + $amount;
        $this->saveItem($id, $value, $ttl);

        return $value;
    }

    /**
     * Atomically decrement a counter in cache, creating it at $initial if it doesn't exist
     *
     * Non-atomic read-modify-write through the same start/ttl/value envelope used by saveItem()/getItem() —
     * Session has no native atomic primitive, so a counter here is an ordinary cached integer, fully
     * interoperable with getItem()/hasItem()/deleteItem().
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
        $current = $this->getItem($id, $initial);

        if (!is_int($current)) {
            throw new Exception('Error: The value at that key is not numeric.');
        }

        $value = $current - $amount;
        $this->saveItem($id, $value, $ttl);

        return $value;
    }

    /**
     * Build the namespaced storage key for an item id
     *
     * Unlike Apc/Memcached/Redis, this adapter's clear() deletes matching keys directly by prefix
     * (a plain PHP array can be enumerated and filtered cheaply) rather than via generational
     * versioning, so this key has no version segment — there's no backend-scan limitation here to
     * work around.
     *
     * @param  string $id
     * @return string
     */
    protected function key(string $id): string
    {
        return $this->namespace . ':' . sha1($id);
    }
}
