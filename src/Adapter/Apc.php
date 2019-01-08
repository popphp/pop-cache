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
 * APC cache adapter class
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.3
 */
class Apc extends AbstractAdapter
{

    /**
     * Flag for APCu
     * @var boolean
     */
    protected $apcu = true;

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
        if (!function_exists('apc_cache_info') && !function_exists('apcu_cache_info')) {
            throw new Exception('Error: APC is not available.');
        }

        $this->apcu = function_exists('apcu_cache_info');
    }

    /**
     * Method to get the current APC info.
     *
     * @return array
     */
    public function getInfo()
    {
        return ($this->apcu) ? apcu_cache_info() : apc_cache_info();
    }

    /**
     * Get the time-to-live for an item in cache
     *
     * @param  string $id
     * @return int
     */
    public function getItemTtl($id)
    {
        $cacheValue = ($this->apcu) ? apcu_fetch($id) : apc_fetch($id);
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

        if ($this->apcu) {
            apcu_store($id, $cacheValue, $cacheValue['ttl']);
        } else {
            apc_store($id, $cacheValue, $cacheValue['ttl']);
        }
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
        $cacheValue = ($this->apcu) ? apcu_fetch($id) : apc_fetch($id);
        $value      = false;

        if (($cacheValue !== false) && (($cacheValue['ttl'] == 0) || ((time() - $cacheValue['start']) <= $cacheValue['ttl']))) {
            $value = $cacheValue['value'];
        } else {
            $this->deleteItem($id);
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
        return ($this->getItem($id) !== false);
    }

    /**
     * Delete a value in cache
     *
     * @param  string $id
     * @return Apc
     */
    public function deleteItem($id)
    {
        if ($this->apcu) {
            apcu_delete($id);
        } else {
            apc_delete($id);
        }
        return $this;
    }

    /**
     * Clear all stored values from cache
     *
     * @return Apc
     */
    public function clear()
    {
        if ($this->apcu) {
            apcu_clear_cache();
        } else {
            apc_clear_cache();
            apc_clear_cache('user');
        }
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
