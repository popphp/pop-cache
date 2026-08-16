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
namespace Pop\Cache;

use Pop\Cache\Adapter\AdapterInterface;
use Pop\Cache\Clock;

/**
 * Cache class
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.0.0
 */
class Cache implements \ArrayAccess, \Psr\SimpleCache\CacheInterface
{

    /**
     * Traits
     */
    use ValidatesKey;

    /**
     * Cache adapter
     * @var ?Adapter\AdapterInterface
     */
    protected ?Adapter\AdapterInterface $adapter = null;

    /**
     * Clock used for stampede-protection bookkeeping (remember()'s $beta parameter)
     * @var Clock\ClockInterface
     */
    protected Clock\ClockInterface $clock;

    /**
     * Constructor
     *
     * Instantiate the cache object
     *
     * @param  Adapter\AdapterInterface $adapter
     * @param  Clock\ClockInterface     $clock
     */
    public function __construct(
        Adapter\AdapterInterface $adapter, Clock\ClockInterface $clock = new Clock\SystemClock()
    )
    {
        $this->adapter = $adapter;
        $this->clock   = $clock;
    }

    /**
     * Determine available adapters
     *
     * @return array
     */
    public static function getAvailableAdapters(): array
    {
        $pdoDrivers = (class_exists('Pdo', false)) ? \PDO::getAvailableDrivers() : [];

        return [
            'apc'       => (function_exists('apc_cache_info')),
            'file'      => true,
            'memcached' => (class_exists('Memcached', false)),
            'memory'    => true,
            'null'      => true,
            'redis'     => (class_exists('Redis', false)),
            'session'   => (function_exists('session_start')),
            'sqlite'    => (class_exists('Sqlite3') || in_array('sqlite', $pdoDrivers))
        ];
    }

    /**
     * Determine if an adapter is available
     *
     * @param  string $adapter
     * @return bool
     */
    public static function isAvailable(string $adapter): bool
    {
        $adapter  = strtolower($adapter);
        $adapters = self::getAvailableAdapters();
        return (isset($adapters[$adapter]) && ($adapters[$adapter]));
    }

    /**
     * Get the adapter
     *
     * @return ?Adapter\AdapterInterface
     */
    public function adapter(): ?Adapter\AdapterInterface
    {
        return $this->adapter;
    }

    /**
     * Get global cache TTL
     *
     * @return int
     */
    public function getTtl(): int
    {
        return $this->adapter->getTtl();
    }

    /**
     * Get item cache TTL
     *
     * @param  string $id
     * @param  int    $default
     * @return int
     */
    public function getItemTtl(string $id, int $default = 0): int
    {
        return $this->adapter->getItemTtl($id, $default);
    }

    /**
     * Save an item to cache
     *
     * @param  string $id
     * @param  mixed  $value
     * @param  ?int   $ttl
     * @throws InvalidArgumentException
     * @return void
     */
    public function saveItem(string $id, mixed $value, ?int $ttl = null): void
    {
        $this->validateKey($id);
        $this->adapter->saveItem($id, $value, $ttl);
    }

    /**
     * Save items to cache
     *
     * @param  array $items
     * @throws InvalidArgumentException
     * @return void
     */
    public function saveItems(array $items): void
    {
        foreach (array_keys($items) as $id) {
            $this->validateKey((string)$id);
        }

        foreach ($items as $id => $value) {
            $this->adapter->saveItem((string)$id, $value);
        }
    }

    /**
     * Get the internal bookkeeping key for a tag's member index
     *
     * @param  string $tag
     * @return string
     */
    protected function tagIndexKey(string $tag): string
    {
        return '@tag:' . sha1($tag);
    }

    /**
     * Get the internal bookkeeping key for an id's own current tag list
     *
     * @param  string $id
     * @return string
     */
    protected function tagsForKey(string $id): string
    {
        return '@tags-for:' . sha1($id);
    }

    /**
     * Add $id to $tag's member index, creating the index if it doesn't exist yet
     *
     * Reads/writes the index directly via the adapter (not $this->getItem()/saveItem()), since the
     * internally-generated key uses '@', a character validateKey() forbids in user-supplied keys. The index
     * is always saved with ttl=0 (never expires), regardless of any individual tagged item's TTL, since
     * multiple items with different TTLs can share one tag.
     *
     * @param  string $tag
     * @param  string $id
     * @return void
     */
    protected function addToTagIndex(string $tag, string $id): void
    {
        $key     = $this->tagIndexKey($tag);
        $members = $this->adapter->getItem($key, []);

        if (!in_array($id, $members, true)) {
            $members[] = $id;
            $this->adapter->saveItem($key, $members, 0);
        }
    }

    /**
     * Remove $id from $tag's member index, deleting the index entirely if it becomes empty
     *
     * @param  string $tag
     * @param  string $id
     * @return void
     */
    protected function removeFromTagIndex(string $tag, string $id): void
    {
        $key     = $this->tagIndexKey($tag);
        $members = array_values(array_diff($this->adapter->getItem($key, []), [$id]));

        if (empty($members)) {
            $this->adapter->deleteItem($key);
        } else {
            $this->adapter->saveItem($key, $members, 0);
        }
    }

    /**
     * Save an item to cache under one or more tags for later bulk invalidation via invalidateTag()
     *
     * Non-atomic: every step here is a plain read-modify-write via the adapter's own getItem()/saveItem()/
     * deleteItem(), with no locking. Mixing plain saveItem()/deleteItem() calls with saveTaggedItem() on the
     * same id leaves tag bookkeeping stale — see docs/POP-CACHE.md for the full mechanism and known
     * limitations. Both the tag index and this id's own tags-for bookkeeping are saved with ttl=0 (never
     * expire) regardless of $ttl, so re-tagging reconciliation always has an accurate view of an id's prior
     * tags even after the item itself has naturally expired — see docs/POP-CACHE.md for why an asymmetric
     * policy here is a correctness bug, not just a minor inconsistency.
     *
     * @param  string $id
     * @param  mixed  $value
     * @param  array  $tags
     * @param  ?int   $ttl
     * @throws InvalidArgumentException
     * @return void
     */
    public function saveTaggedItem(string $id, mixed $value, array $tags, ?int $ttl = null): void
    {
        $this->validateKey($id);
        foreach ($tags as $tag) {
            if (!is_string($tag)) {
                throw new InvalidArgumentException('Error: Every tag must be a string.');
            }
            $this->validateKey($tag);
        }

        $tagsForKey = $this->tagsForKey($id);
        $oldTags    = $this->adapter->getItem($tagsForKey, []);

        foreach (array_diff($oldTags, $tags) as $oldTag) {
            $this->removeFromTagIndex($oldTag, $id);
        }

        foreach ($tags as $tag) {
            $this->addToTagIndex($tag, $id);
        }

        $this->adapter->saveItem($id, $value, $ttl);

        if (empty($tags)) {
            $this->adapter->deleteItem($tagsForKey);
        } else {
            $this->adapter->saveItem($tagsForKey, $tags, 0);
        }
    }

    /**
     * Get an item from cache
     *
     * @param  string $id
     * @param  mixed  $default
     * @throws InvalidArgumentException
     * @return mixed
     */
    public function getItem(string $id, mixed $default = false): mixed
    {
        $this->validateKey($id);
        return $this->adapter->getItem($id, $default);
    }

    /**
     * Get the internal bookkeeping key for an id's stampede-protection metadata
     *
     * @param  string $id
     * @return string
     */
    protected function rememberMetaKey(string $id): string
    {
        return '@remember:' . sha1($id);
    }

    /**
     * Get an item from cache, or compute and cache it via the callback if it isn't already cached
     *
     * Any exception thrown by $callback propagates unchanged, and nothing is written to cache in that case.
     *
     * $beta enables probabilistic early recomputation (the XFetch algorithm) to reduce stampedes on
     * steady-state re-computation: as a cached item approaches its TTL, reads have an increasing chance of
     * triggering recomputation before hard expiry, so one caller refreshes the value while concurrent readers
     * keep getting served the still-valid cached copy. $beta = 0.0 (the default) disables this entirely —
     * mathematically equivalent to remember()'s behavior before this parameter existed, with zero extra
     * adapter calls. This does NOT protect a key's true first-ever call (no cached value, no bookkeeping yet)
     * — every concurrent caller on a genuine cold miss still recomputes; that requires locking, which this
     * method deliberately does not implement. Never-expiring items (a resolved TTL of 0, whether explicit or
     * from the adapter's global TTL) are not eligible for early recomputation — there's no expiry to recompute
     * ahead of, so $beta has no effect on them. A callback that completes in under one second still gets a
     * minimum effective delta of 1 second for the XFetch calculation, so $beta needs to be scaled accordingly
     * for very fast callbacks to see meaningful early-recompute lead time (roughly delta × beta seconds before
     * real expiry). See docs/POP-CACHE.md for the full algorithm and why the bookkeeping key's TTL policy
     * differs from saveTaggedItem()'s.
     *
     * @param  string   $id
     * @param  callable $callback
     * @param  ?int     $ttl
     * @param  float    $beta
     * @throws InvalidArgumentException
     * @return mixed
     */
    public function remember(string $id, callable $callback, ?int $ttl = null, float $beta = 0.0): mixed
    {
        $miss  = new \stdClass();
        $value = $this->getItem($id, $miss);

        $shouldRecompute = ($value === $miss);

        if (!$shouldRecompute && ($beta > 0.0)) {
            $meta = $this->adapter->getItem($this->rememberMetaKey($id), null);

            if (is_array($meta) && ($meta['ttl'] > 0)) {
                $rand   = mt_rand(1, mt_getrandmax()) / mt_getrandmax();
                $expiry = $meta['start'] + $meta['ttl'];

                if (($this->clock->now() - ($meta['delta'] * $beta * log($rand))) >= $expiry) {
                    $shouldRecompute = true;
                }
            }
        }

        if ($shouldRecompute) {
            $start = $this->clock->now();
            $value = $callback();
            $delta = max(1, $this->clock->now() - $start);

            $resolvedTtl = $ttl ?? $this->adapter->getTtl();

            $this->saveItem($id, $value, $ttl);

            if (($beta > 0.0) && ($resolvedTtl > 0)) {
                $this->adapter->saveItem(
                    $this->rememberMetaKey($id),
                    ['start' => $start, 'ttl' => $resolvedTtl, 'delta' => $delta],
                    $resolvedTtl
                );
            }
        }

        return $value;
    }

    /**
     * Determine if the item is in cache
     *
     * @param  string $id
     * @throws InvalidArgumentException
     * @return bool
     */
    public function hasItem(string $id): bool
    {
        $this->validateKey($id);
        return $this->adapter->hasItem($id);
    }

    /**
     * Atomically increment a counter in cache, creating it at $initial if it doesn't exist
     *
     * @param  string $id
     * @param  int    $amount
     * @param  int    $initial
     * @param  ?int   $ttl
     * @throws InvalidArgumentException
     * @return int
     */
    public function incrementItem(string $id, int $amount = 1, int $initial = 0, ?int $ttl = null): int
    {
        $this->validateKey($id);
        return $this->adapter->incrementItem($id, $amount, $initial, $ttl);
    }

    /**
     * Atomically decrement a counter in cache, creating it at $initial if it doesn't exist
     *
     * @param  string $id
     * @param  int    $amount
     * @param  int    $initial
     * @param  ?int   $ttl
     * @throws InvalidArgumentException
     * @return int
     */
    public function decrementItem(string $id, int $amount = 1, int $initial = 0, ?int $ttl = null): int
    {
        $this->validateKey($id);
        return $this->adapter->decrementItem($id, $amount, $initial, $ttl);
    }

    /**
     * Fetch a value from the cache (PSR-16)
     *
     * @param  string $key
     * @param  mixed  $default
     * @throws InvalidArgumentException
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);
        return $this->adapter->getItem($key, $default);
    }

    /**
     * Obtain multiple cache items by their unique keys (PSR-16)
     *
     * @param  iterable $keys
     * @param  mixed    $default
     * @throws InvalidArgumentException
     * @return iterable
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $keys = is_array($keys) ? $keys : iterator_to_array($keys, false);

        foreach ($keys as $key) {
            $this->validateKey((string)$key);
        }

        $values = [];
        foreach ($keys as $key) {
            $values[(string)$key] = $this->adapter->getItem((string)$key, $default);
        }

        return $values;
    }

    /**
     * Persist a value in the cache, with an optional TTL (PSR-16)
     *
     * A $ttl of null uses the adapter's configured global TTL. A $ttl (or a \DateInterval resolved to
     * seconds) that is zero or negative deletes the item instead of caching it, per the PSR-16 spec —
     * note this differs from saveItem()'s own "0 = never expires" convention, which is unaffected by this
     * method and remains available via saveItem() directly.
     *
     * @param  string                 $key
     * @param  mixed                  $value
     * @param  null|int|\DateInterval $ttl
     * @throws InvalidArgumentException
     * @return bool
     */
    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $this->validateKey($key);

        $seconds = $ttl;
        if ($ttl instanceof \DateInterval) {
            $now     = new \DateTimeImmutable();
            $seconds = $now->add($ttl)->getTimestamp() - $now->getTimestamp();
        }

        if (($seconds !== null) && ($seconds <= 0)) {
            $this->adapter->deleteItem($key);
        } else {
            $this->adapter->saveItem($key, $value, $seconds);
        }

        return true;
    }

    /**
     * Persist a set of key => value pairs in the cache, with an optional TTL (PSR-16)
     *
     * @param  iterable               $values
     * @param  null|int|\DateInterval $ttl
     * @throws InvalidArgumentException
     * @return bool
     */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        $values = is_array($values) ? $values : iterator_to_array($values);

        foreach (array_keys($values) as $key) {
            $this->validateKey((string)$key);
        }

        foreach ($values as $key => $value) {
            $this->set((string)$key, $value, $ttl);
        }

        return true;
    }

    /**
     * Determine whether an item is present in the cache (PSR-16)
     *
     * @param  string $key
     * @throws InvalidArgumentException
     * @return bool
     */
    public function has(string $key): bool
    {
        $this->validateKey($key);
        return $this->adapter->hasItem($key);
    }

    /**
     * Delete an item in cache
     *
     * @param  string $id
     * @throws InvalidArgumentException
     * @return void
     */
    public function deleteItem(string $id): void
    {
        $this->validateKey($id);
        $this->adapter->deleteItem($id);
    }

    /**
     * Delete an item from the cache (PSR-16)
     *
     * @param  string $key
     * @throws InvalidArgumentException
     * @return bool
     */
    public function delete(string $key): bool
    {
        $this->validateKey($key);
        $this->adapter->deleteItem($key);
        return true;
    }

    /**
     * Delete multiple cache items in a single operation (PSR-16)
     *
     * @param  iterable $keys
     * @throws InvalidArgumentException
     * @return bool
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $keys = is_array($keys) ? $keys : iterator_to_array($keys, false);

        foreach ($keys as $key) {
            $this->validateKey($key);
        }

        foreach ($keys as $key) {
            $this->adapter->deleteItem($key);
        }

        return true;
    }

    /**
     * Delete items in cache
     *
     * @param  array $ids
     * @throws InvalidArgumentException
     * @return void
     */
    public function deleteItems(array $ids): void
    {
        foreach ($ids as $id) {
            $this->validateKey($id);
        }

        foreach ($ids as $id) {
            $this->adapter->deleteItem($id);
        }
    }

    /**
     * Delete every item currently tagged with $tag, and the tag's own bookkeeping
     *
     * Non-atomic: see saveTaggedItem() and docs/POP-CACHE.md for the full mechanism and known limitations.
     *
     * @param  string $tag
     * @throws InvalidArgumentException
     * @return void
     */
    public function invalidateTag(string $tag): void
    {
        $this->validateKey($tag);

        $tagKey  = $this->tagIndexKey($tag);
        $members = $this->adapter->getItem($tagKey, []);

        foreach ($members as $id) {
            $tagsForKey = $this->tagsForKey($id);
            $idTags     = $this->adapter->getItem($tagsForKey, []);

            foreach ($idTags as $otherTag) {
                if ($otherTag !== $tag) {
                    $this->removeFromTagIndex($otherTag, $id);
                }
            }

            $this->adapter->deleteItem($id);
            $this->adapter->deleteItem($tagsForKey);
        }

        $this->adapter->deleteItem($tagKey);
    }

    /**
     * Invalidate multiple tags in a single operation
     *
     * Validates every tag before invalidating any of them (all-or-nothing), matching this codebase's
     * existing getMultiple()/setMultiple()/deleteMultiple() precedent.
     *
     * @param  array $tags
     * @throws InvalidArgumentException
     * @return void
     */
    public function invalidateTags(array $tags): void
    {
        foreach ($tags as $tag) {
            if (!is_string($tag)) {
                throw new InvalidArgumentException('Error: Every tag must be a string.');
            }
            $this->validateKey($tag);
        }

        foreach ($tags as $tag) {
            $this->invalidateTag($tag);
        }
    }

    /**
     * Clear all stored values from cache
     *
     * @return bool
     */
    public function clear(): bool
    {
        $this->adapter->clear();
        return true;
    }

    /**
     * Destroy cache resource
     *
     * @return void
     */
    public function destroy(): void
    {
        $this->adapter->destroy();
    }

    /**
     * Magic get method to return an item from cache
     *
     * @param  string $name
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return $this->getItem($name);
    }

    /**
     * Magic set method to save an item in the cache
     *
     * @param  string $name
     * @param  mixed $value
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        $this->saveItem($name, $value);
    }

    /**
     * Determine if the item is in cache
     *
     * @param  string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return $this->hasItem($name);
    }

    /**
     * Delete value from cache
     *
     * @param  string $name
     * @return void
     */
    public function __unset(string $name): void
    {
        $this->deleteItem($name);
    }

    /**
     * ArrayAccess offsetExists
     *
     * @param  mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->__isset($offset);
    }

    /**
     * ArrayAccess offsetGet
     *
     * @param  mixed $offset
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->__get($offset);
    }

    /**
     * ArrayAccess offsetSet
     *
     * @param  mixed $offset
     * @param  mixed $value
     * @return void
     */
    public function offsetSet(mixed $offset, mixed  $value): void
    {
        $this->__set($offset, $value);
    }

    /**
     * ArrayAccess offsetUnset
     *
     * @param  mixed $offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->__unset($offset);
    }

}
