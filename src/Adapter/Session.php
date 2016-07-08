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
 * Session adapter cache class
 *
 * @category   Pop
 * @package    Pop_Cache
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2016 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.2.0
 */
class Session extends AbstractAdapter
{

    /**
     * Constructor
     *
     * Instantiate the cache session object
     *
     * @param  int $lifetime
     * @return Session
     */
    public function __construct($lifetime = 0)
    {
        parent::__construct($lifetime);
        if (session_id() == '') {
            session_start();
        }
        if (!isset($_SESSION['_POP_CACHE'])) {
            $_SESSION['_POP_CACHE'] = [];
        }
    }

    /**
     * Save a value to cache.
     *
     * @param  string $id
     * @param  mixed  $value
     * @return Session
     */
    public function save($id, $value)
    {
        $cacheValue = [
            'start'    => time(),
            'expire'   => ($this->lifetime != 0) ? time() + $this->lifetime : 0,
            'lifetime' => $this->lifetime,
            'value'    => $value
        ];

        $_SESSION['_POP_CACHE'][$id] = serialize($cacheValue);

        return $this;
    }

    /**
     * Load a value from cache.
     *
     * @param  string $id
     * @return mixed
     */
    public function load($id)
    {
        $value  = false;

        if (isset($_SESSION['_POP_CACHE'][$id])) {
            $cacheValue = unserialize($_SESSION['_POP_CACHE'][$id]);
            if (($cacheValue['expire'] == 0) || ((time() - $cacheValue['start']) <= $cacheValue['lifetime'])) {
                $value = $cacheValue['value'];
            } else {
                $this->remove($id);
            }
        }

        return $value;
    }

    /**
     * Remove a value in cache.
     *
     * @param  string $id
     * @return Session
     */
    public function remove($id)
    {
        if (isset($_SESSION['_POP_CACHE'][$id])) {
            unset($_SESSION['_POP_CACHE'][$id]);
        }

        return $this;
    }

    /**
     * Clear all stored values from cache.
     *
     * @return Session
     */
    public function clear()
    {
        $_SESSION['_POP_CACHE'] = [];
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
        $value = 0;

        if (isset($_SESSION['_POP_CACHE'][$id])) {
            $cacheValue = unserialize($_SESSION['_POP_CACHE'][$id]);
            $value      = $cacheValue['start'];
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
        $value = 0;

        if (isset($_SESSION['_POP_CACHE'][$id])) {
            $cacheValue = unserialize($_SESSION['_POP_CACHE'][$id]);
            $value      = $cacheValue['expire'];
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
        $value = 0;

        if (isset($_SESSION['_POP_CACHE'][$id])) {
            $cacheValue = unserialize($_SESSION['_POP_CACHE'][$id]);
            $value      = $cacheValue['lifetime'];
        }

        return $value;
    }

}
