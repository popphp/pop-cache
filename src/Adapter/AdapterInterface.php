<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Cache\Adapter;

/**
 * Cache adapter interface
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.3.0
 */
interface AdapterInterface
{

    /**
     * Set the global time-to-live for the cache adapter
     *
     * @param  int $ttl
     * @return AdapterInterface
     */
    public function setTtl($ttl);

    /**
     * Get the global time-to-live for the cache object
     *
     * @return int
     */
    public function getTtl();

    /**
     * Get the time-to-live for an item in cache
     *
     * @param  string $id
     * @return int
     */
    public function getItemTtl($id);

    /**
     * Save an item to cache
     *
     * @param  string $id
     * @param  mixed  $value
     * @param  int    $ttl
     * @return void
     */
    public function saveItem($id, $value, $ttl = null);

    /**
     * Get an item from cache
     *
     * @param  string $id
     * @return mixed
     */
    public function getItem($id);

    /**
     * Determine if the item exist in cache
     *
     * @param  string $id
     * @return boolean
     */
    public function hasItem($id);

    /**
     * Delete a value in cache
     *
     * @param  string $id
     * @return void
     */
    public function deleteItem($id);

    /**
     * Clear all stored values from cache
     *
     * @return void
     */
    public function clear();

    /**
     * Destroy cache resource
     *
     * @return void
     */
    public function destroy();

}
