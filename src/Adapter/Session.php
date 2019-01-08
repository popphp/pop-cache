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
 * Session adapter cache class
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.3
 */
class Session extends AbstractAdapter
{

    /**
     * Constructor
     *
     * Instantiate the cache session object
     *
     * @param  int $ttl
     */
    public function __construct($ttl = 0)
    {
        parent::__construct($ttl);
        if (session_id() == '') {
            session_start();
        }
        if (!isset($_SESSION['_POP_CACHE_'])) {
            $_SESSION['_POP_CACHE_'] = [];
        }
    }

    /**
     * Get the time-to-live for an item in cache
     *
     * @param  string $id
     * @return int
     */
    public function getItemTtl($id)
    {
        $ttl = 0;

        if (isset($_SESSION['_POP_CACHE_'][$id])) {
            $cacheValue = unserialize($_SESSION['_POP_CACHE_'][$id]);
            $ttl        = $cacheValue['ttl'];
        }

        return $ttl;
    }

    /**
     * Save an item to cache
     *
     * @param  string $id
     * @param  mixed  $value
     * @param  int    $ttl
     * @return Session
     */
    public function saveItem($id, $value, $ttl = null)
    {
        $_SESSION['_POP_CACHE_'][$id] = serialize([
            'start' => time(),
            'ttl'   => (null !== $ttl) ? (int)$ttl : $this->ttl,
            'value' => $value
        ]);

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
        $value  = false;

        if (isset($_SESSION['_POP_CACHE_'][$id])) {
            $cacheValue = unserialize($_SESSION['_POP_CACHE_'][$id]);
            if (($cacheValue['ttl'] == 0) || ((time() - $cacheValue['start']) <= $cacheValue['ttl'])) {
                $value = $cacheValue['value'];
            } else {
                $this->deleteItem($id);
            }
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
        $result = false;

        if (isset($_SESSION['_POP_CACHE_'][$id])) {
            $cacheValue = unserialize($_SESSION['_POP_CACHE_'][$id]);
            $result = (($cacheValue['ttl'] == 0) || ((time() - $cacheValue['start']) <= $cacheValue['ttl']));
        }

        return $result;
    }

    /**
     * Delete a value in cache
     *
     * @param  string $id
     * @return Session
     */
    public function deleteItem($id)
    {
        if (isset($_SESSION['_POP_CACHE_'][$id])) {
            unset($_SESSION['_POP_CACHE_'][$id]);
        }

        return $this;
    }

    /**
     * Clear all stored values from cache
     *
     * @return Session
     */
    public function clear()
    {
        $_SESSION['_POP_CACHE_'] = [];
        return $this;
    }

    /**
     * Destroy cache resource
     *
     * @return void
     */
    public function destroy()
    {
        $_SESSION = null;
        session_unset();
        session_destroy();
    }
}
