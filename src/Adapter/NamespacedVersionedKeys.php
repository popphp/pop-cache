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

/**
 * Shared namespace/version key-building for adapters that scope clear()/destroy() to a namespace via
 * generational versioning (Apc, Memcached, Redis) rather than wiping the whole shared backend
 *
 * Using classes must have a `protected string $namespace` property and implement fetchVersion() to read
 * the raw version value back from their own backend.
 *
 * The resolved version is memoized for the lifetime of the adapter instance. Without it, every single
 * call that builds a key -- saveItem(), getItem(), hasItem(), deleteItem(), getItemTtl(),
 * incrementItem() -- pays a round trip to the backend to re-read a counter that changes only when
 * clear() is called, doubling the backend traffic of the entire component. Instances created before a
 * clear() elsewhere keep serving the version they resolved; see nextVersion() for why that is the
 * intended trade.
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.0.0
 */
trait NamespacedVersionedKeys
{

    /**
     * Memoized namespace version for this instance, or null if not yet resolved
     *
     * Deliberately NOT named $version: Pop\Cache\Adapter\Memcached already declares a
     * `protected ?string $version` holding the memcached *server* version string, and a trait
     * property colliding with a differently-typed class property is a fatal error.
     *
     * @var ?int
     */
    protected ?int $resolvedVersion = null;

    /**
     * Fetch the raw version value from the backend, or false if it isn't set
     *
     * @param  string $key
     * @return mixed
     */
    abstract protected function fetchVersion(string $key): mixed;

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
     * Reads the backend once per instance and memorizes the answer. Call forgetVersion() to force the
     * next call to re-read.
     *
     * @return int
     */
    protected function resolveVersion(): int
    {
        if ($this->resolvedVersion === null) {
            $version               = $this->fetchVersion($this->versionKey());
            $this->resolvedVersion = ($version !== false) ? (int)$version : 1;
        }

        return $this->resolvedVersion;
    }

    /**
     * Bump this namespace to its next version and memoize it
     *
     * Used by clear(): the caller writes the returned value to the backend, and this instance starts
     * building keys against it immediately rather than re-reading what it just wrote.
     *
     * A *different* live instance sharing this namespace keeps the version it already resolved, so it
     * goes on reading and writing the pre-clear generation until it is discarded. That is the accepted
     * cost of not paying a round trip per key: the generational scheme has never promised cross-process
     * immediacy either -- another PHP-FPM worker mid-request has always had the same stale view -- and
     * the entries it touches still expire on their own TTL. Code that must observe a clear() performed
     * elsewhere should call forgetVersion() first.
     *
     * @return int
     */
    protected function nextVersion(): int
    {
        $this->resolvedVersion = $this->resolveVersion() + 1;

        return $this->resolvedVersion;
    }

    /**
     * Discard the memoized version so the next key build re-reads it from the backend
     *
     * @return void
     */
    protected function forgetVersion(): void
    {
        $this->resolvedVersion = null;
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
