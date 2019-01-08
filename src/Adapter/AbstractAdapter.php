<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
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
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.3
 */
abstract class AbstractAdapter implements AdapterInterface
{

    /**
     * Global time-to-live
     * @var int
     */
    protected $ttl = 0;

    /**
     * Constructor
     *
     * Instantiate the cache adapter object
     *
     * @param  int $ttl
     */
    public function __construct($ttl = 0)
    {
        $this->setTtl($ttl);
    }

    /**
     * Set the global time-to-live for the cache adapter
     *
     * @param  int $ttl
     * @return AbstractAdapter
     */
    public function setTtl($ttl)
    {
        $this->ttl = (int)$ttl;
        return $this;
    }

    /**
     * Get the global time-to-live for the cache object
     *
     * @return int
     */
    public function getTtl()
    {
        return $this->ttl;
    }

    /**
     * Get the time-to-live for an item in cache
     *
     * @param  string $id
     * @return int
     */
    abstract public function getItemTtl($id);

    /**
     * Save an item to cache
     *
     * @param  string $id
     * @param  mixed  $value
     * @param  int    $ttl
     * @return void
     */
    abstract public function saveItem($id, $value, $ttl = null);

    /**
     * Get an item from cache
     *
     * @param  string $id
     * @return mixed
     */
    abstract public function getItem($id);

    /**
     * Determine if the item exist in cache
     *
     * @param  string $id
     * @return boolean
     */
    abstract public function hasItem($id);

    /**
     * Delete a value in cache
     *
     * @param  string $id
     * @return void
     */
    abstract public function deleteItem($id);

    /**
     * Clear all stored values from cache
     *
     * @return void
     */
    abstract public function clear();

    /**
     * Destroy cache resource
     *
     * @return void
     */
    abstract public function destroy();

}
