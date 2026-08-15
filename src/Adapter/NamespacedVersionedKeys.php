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

/**
 * Shared namespace/version key-building for adapters that scope clear()/destroy() to a namespace via
 * generational versioning (Apc, Memcached, Redis) rather than wiping the whole shared backend
 *
 * Using classes must have a `protected string $namespace` property and implement fetchVersion() to read
 * the raw version value back from their own backend.
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.0.0
 */
trait NamespacedVersionedKeys
{

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
     * @return int
     */
    protected function resolveVersion(): int
    {
        $version = $this->fetchVersion($this->versionKey());
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
