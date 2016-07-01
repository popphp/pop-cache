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
 * @package    Pop_Cache
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2016 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.1.0
 */
class Apc extends AbstractAdapter
{

    /**
     * APC info
     * @var array
     */
    protected $info = null;

    /**
     * Constructor
     *
     * Instantiate the APC cache object
     *
     * @param  int $lifetime
     * @throws Exception
     * @return Apc
     */
    public function __construct($lifetime = 0)
    {
        parent::__construct($lifetime);
        if (!function_exists('apc_cache_info')) {
            throw new Exception('Error: APC is not available.');
        }
        $this->info = apc_cache_info();
    }

    /**
     * Method to get the current APC info.
     *
     * @return string
     */
    public function getInfo()
    {
        return $this->info;
    }

    /**
     * Method to save a value to cache.
     *
     * @param  string $id
     * @param  mixed  $value
     * @return Apc
     */
    public function save($id, $value)
    {
        $cacheValue = [
            'start'    => time(),
            'expire'   => ($this->lifetime != 0) ? time() + $this->lifetime : 0,
            'lifetime' => $this->lifetime,
            'value'    => $value
        ];

        apc_store($id, $cacheValue, $this->lifetime);
        return $this;
    }

    /**
     * Method to load a value from cache.
     *
     * @param  string $id
     * @return mixed
     */
    public function load($id)
    {
        $cacheValue = apc_fetch($id);
        $value      = false;

        if ($cacheValue !== false) {
            $value = $cacheValue['value'];
        }

        return $value;
    }

    /**
     * Method to delete a value in cache.
     *
     * @param  string $id
     * @return Apc
     */
    public function remove($id)
    {
        apc_delete($id);
        return $this;
    }

    /**
     * Method to clear all stored values from cache.
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
     * Tell is a value is expired.
     *
     * @param  string $id
     * @return boolean
     */
    public function isExpired($id)
    {
        return ($this->load($id) === false);
    }

    /**
     * Get original start timestamp of the value.
     *
     * @param  string $id
     * @return int
     */
    public function getStart($id)
    {
        $cacheValue = apc_fetch($id);
        $value      = 0;

        if ($cacheValue !== false) {
            $value = $cacheValue['start'];
        }

        return $value;
    }

    /**
     * Get expiration timestamp of the value.
     *
     * @param  string $id
     * @return int
     */
    public function getExpiration($id)
    {
        $cacheValue = apc_fetch($id);
        $value      = 0;

        if ($cacheValue !== false) {
            $value = $cacheValue['expire'];
        }

        return $value;
    }

    /**
     * Get the lifetime of the value.
     *
     * @param  string $id
     * @return int
     */
    public function getLifetime($id)
    {
        $cacheValue = apc_fetch($id);
        $value      = 0;

        if ($cacheValue !== false) {
            $value = $cacheValue['lifetime'];
        }

        return $value;
    }

}
