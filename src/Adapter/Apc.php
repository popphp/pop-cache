<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2016 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Cache\Adapter;

/**
 * APC cache adapter class
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2016 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.1.0
 */
class Apc extends AbstractAdapter
{

    /**
     * Constructor
     *
     * Instantiate the APC cache object
     *
     * @param  int $ttl
     * @throws Exception
     */
    public function __construct($ttl = 0)
    {
        parent::__construct($ttl);
        if (!function_exists('apc_cache_info')) {
            throw new Exception('Error: APC is not available.');
        }
    }

    /**
     * Method to get the current APC info.
     *
     * @return array
     */
    public function getInfo()
    {
        return apc_cache_info();
    }

    /**
     * Get the time-to-live for an item in cache
     *
     * @param  string $id
     * @return int
     */
    public function getItemTtl($id)
    {
        $cacheValue = apc_fetch($id);
        $ttl        = 0;

        if ($cacheValue !== false) {
            $ttl = $cacheValue['ttl'];
        }

        return $ttl;
    }

    /**
     * Save an item to cache
     *
     * @param  string $id
     * @param  mixed  $value
     * @param  int    $ttl
     * @return Apc
     */
    public function saveItem($id, $value, $ttl = null)
    {
        $cacheValue = [
            'start' => time(),
            'ttl'   => (null !== $ttl) ? (int)$ttl : $this->ttl,
            'value' => $value
        ];

        apc_store($id, $cacheValue, $cacheValue['ttl']);
        return $this;
    }

    /**
     * Get an item from cache
     *
     * @param  string $id
     * @return mixed
     */
    public function getItem($id)
    {
        $cacheValue = apc_fetch($id);
        $value      = false;

        if ($cacheValue !== false) {
            $value = $cacheValue['value'];
        }

        return $value;
    }

    /**
     * Determine if the item exist in cache
     *
     * @param  string $id
     * @return boolean
     */
    public function hasItem($id)
    {
        $cacheValue = apc_fetch($id);
        return ($cacheValue !== false);
    }

    /**
     * Delete a value in cache
     *
     * @param  string $id
     * @return Apc
     */
    public function deleteItem($id)
    {
        apc_delete($id);
        return $this;
    }

    /**
     * Clear all stored values from cache
     *
     * @return Apc
     */
    public function clear()
    {
        apc_clear_cache();
        apc_clear_cache('user');
        return $this;
    }

    /**
     * Destroy cache resource
     *
     * @return Apc
     */
    public function destroy()
    {
        $this->clear();
        return $this;
    }

}
