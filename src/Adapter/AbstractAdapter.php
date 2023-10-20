<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Cache\Adapter;

/**
 * Cache adapter abstract class
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    4.0.0
 */
abstract class AbstractAdapter implements AdapterInterface
{

    /**
     * Global time-to-live
     * @var int
     */
    protected int $ttl = 0;

    /**
     * Constructor
     *
     * Instantiate the cache adapter object
     *
     * @param  int $ttl
     */
    public function __construct(int $ttl = 0)
    {
        $this->setTtl($ttl);
    }

    /**
     * Set the global time-to-live for the cache adapter
     *
     * @param  int $ttl
     * @return AbstractAdapter
     */
    public function setTtl(int $ttl): AbstractAdapter
    {
        $this->ttl = $ttl;
        return $this;
    }

    /**
     * Get the global time-to-live for the cache object
     *
     * @return int
     */
    public function getTtl(): int
    {
        return $this->ttl;
    }

    /**
     * Get the time-to-live for an item in cache
     *
     * @param  string $id
     * @return int
     */
    abstract public function getItemTtl(string $id): int;

    /**
     * Save an item to cache
     *
     * @param  string $id
     * @param  mixed  $value
     * @param  ?int   $ttl
     * @return AbstractAdapter
     */
    abstract public function saveItem(string $id, mixed $value, ?int $ttl = null): AbstractAdapter;

    /**
     * Get an item from cache
     *
     * @param  string $id
     * @return mixed
     */
    abstract public function getItem(string $id): mixed;

    /**
     * Determine if the item exist in cache
     *
     * @param  string $id
     * @return bool
     */
    abstract public function hasItem(string $id): bool;

    /**
     * Delete a value in cache
     *
     * @param  string $id
     * @return AbstractAdapter
     */
    abstract public function deleteItem(string $id): AbstractAdapter;

    /**
     * Clear all stored values from cache
     *
     * @return AbstractAdapter
     */
    abstract public function clear(): AbstractAdapter;

    /**
     * Destroy cache resource
     *
     * @return AbstractAdapter
     */
    abstract public function destroy(): AbstractAdapter;

}
