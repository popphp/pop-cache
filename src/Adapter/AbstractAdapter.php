<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2015 NOLA Interactive, LLC. (http://www.nolainteractive.com)
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
 * @package    Pop_Cache
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2015 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.1
 */
abstract class AbstractAdapter implements AdapterInterface
{

    /**
     * Cache lifetime
     * @var int
     */
    protected $lifetime = 0;

    /**
     * Constructor
     *
     * Instantiate the cache object
     *
     * @param  int $lifetime
     * @return AbstractAdapter
     */
    public function __construct($lifetime = 0)
    {
        $this->setLifetime($lifetime);
    }

    /**
     * Set the lifetime.
     *
     * @param  int $lifetime
     * @return AbstractAdapter
     */
    public function setLifetime($lifetime)
    {
        $this->lifetime = (int)$lifetime;
        return $this;
    }

    /**
     * Save a value to cache.
     *
     * @param  string $id
     * @param  mixed  $value
     * @return void
     */
    abstract public function save($id, $value);

    /**
     * Load a value from cache.
     *
     * @param  string $id
     * @return mixed
     */
    abstract public function load($id);

    /**
     * Remove a value in cache.
     *
     * @param  string $id
     * @return void
     */
    abstract public function remove($id);

    /**
     * Clear all stored values from cache.
     *
     * @return void
     */
    abstract public function clear();

    /**
     * Tell is a value is expired.
     *
     * @param  string $id
     * @return boolean
     */
    abstract public function isExpired($id);

    /**
     * Get original start timestamp of the value.
     *
     * @param  string $id
     * @return int
     */
    abstract public function getStart($id);

    /**
     * Get expiration timestamp of the value.
     *
     * @param  string $id
     * @return int
     */
    abstract public function getExpiration($id);

    /**
     * Get the lifetime of the value.
     *
     * @param  string $id
     * @return int
     */
    abstract public function getLifetime($id);

}
