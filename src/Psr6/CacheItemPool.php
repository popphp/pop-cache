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
namespace Pop\Cache\Psr6;

use Pop\Cache\Adapter;
use Pop\Cache\InvalidArgumentException;
use Pop\Cache\ValidatesKey;

/**
 * PSR-6 cache item pool class
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.0.0
 */
class CacheItemPool implements \Psr\Cache\CacheItemPoolInterface
{

    /**
     * Traits
     */
    use ValidatesKey;

    /**
     * Cache adapter
     * @var Adapter\AdapterInterface
     */
    protected Adapter\AdapterInterface $adapter;

    /**
     * Deferred cache items, keyed by item key
     * @var array
     */
    protected array $deferred = [];

    /**
     * Constructor
     *
     * Instantiate the cache item pool object
     *
     * @param  Adapter\AdapterInterface $adapter
     */
    public function __construct(Adapter\AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * Destructor
     *
     * Ensure any deferred cache items are persisted before the pool is destroyed, per PSR-6 §1.4
     * ("A Pool MUST ensure that any deferred cache items are eventually persisted"). A destructor
     * must never let an exception escape, so failures here are swallowed.
     *
     * @return void
     */
    public function __destruct()
    {
        try {
            $this->commit();
        } catch (\Throwable $e) {
            // Destructors must not throw
        }
    }

    /**
     * Get a cache item by key
     *
     * @param  string $key
     * @throws InvalidArgumentException
     * @return CacheItem
     */
    public function getItem(string $key): CacheItem
    {
        $this->validateKey($key);

        if (isset($this->deferred[$key])) {
            return $this->deferred[$key];
        }

        $miss  = new \stdClass();
        $value = $this->adapter->getItem($key, $miss);

        return ($value === $miss) ? new CacheItem($key, null, false) : new CacheItem($key, $value, true);
    }

    /**
     * Get multiple cache items by their keys
     *
     * @param  array $keys
     * @throws InvalidArgumentException
     * @return iterable
     */
    public function getItems(array $keys = []): iterable
    {
        foreach ($keys as $key) {
            $this->validateKey($key);
        }

        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key);
        }

        return $items;
    }

    /**
     * Determine if the cache contains an item for the given key
     *
     * @param  string $key
     * @throws InvalidArgumentException
     * @return bool
     */
    public function hasItem(string $key): bool
    {
        $this->validateKey($key);

        if (isset($this->deferred[$key])) {
            return true;
        }

        return $this->adapter->hasItem($key);
    }

    /**
     * Delete an item from the cache
     *
     * @param  string $key
     * @throws InvalidArgumentException
     * @return bool
     */
    public function deleteItem(string $key): bool
    {
        $this->validateKey($key);
        $this->adapter->deleteItem($key);
        unset($this->deferred[$key]);

        return true;
    }

    /**
     * Delete multiple items from the cache
     *
     * @param  array $keys
     * @throws InvalidArgumentException
     * @return bool
     */
    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->validateKey($key);
        }

        foreach ($keys as $key) {
            $this->deleteItem($key);
        }

        return true;
    }

    /**
     * Clear all stored values from cache
     *
     * @return bool
     */
    public function clear(): bool
    {
        $this->adapter->clear();
        $this->deferred = [];

        return true;
    }

    /**
     * Persist a cache item immediately
     *
     * A resolved expiration of zero or negative seconds deletes the item instead of caching it
     * permanently, since the underlying adapter's saveItem() treats a TTL of 0 as "never expires" —
     * the opposite of what an already-expired item should do.
     *
     * @param  \Psr\Cache\CacheItemInterface $item
     * @throws InvalidArgumentException
     * @return bool
     */
    public function save(\Psr\Cache\CacheItemInterface $item): bool
    {
        if (!($item instanceof CacheItem)) {
            throw new InvalidArgumentException(
                'Error: The cache item must be an instance of ' . CacheItem::class . '.'
            );
        }

        $this->validateKey($item->getKey());

        $seconds = $item->getExpirationSeconds();

        if (($seconds !== null) && ($seconds <= 0)) {
            $this->adapter->deleteItem($item->getKey());
        } else {
            $this->adapter->saveItem($item->getKey(), $item->get(), $seconds);
        }

        unset($this->deferred[$item->getKey()]);

        return true;
    }

    /**
     * Queue a cache item to be persisted later
     *
     * @param  \Psr\Cache\CacheItemInterface $item
     * @throws InvalidArgumentException
     * @return bool
     */
    public function saveDeferred(\Psr\Cache\CacheItemInterface $item): bool
    {
        if (!($item instanceof CacheItem)) {
            throw new InvalidArgumentException(
                'Error: The cache item must be an instance of ' . CacheItem::class . '.'
            );
        }

        $this->validateKey($item->getKey());
        $this->deferred[$item->getKey()] = $item;

        return true;
    }

    /**
     * Persist all deferred cache items
     *
     * @return bool
     */
    public function commit(): bool
    {
        $result = true;
        $items  = $this->deferred;

        foreach ($items as $item) {
            $result = $this->save($item) && $result;
        }

        $this->deferred = [];

        return $result;
    }

}
