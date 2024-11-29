<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
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
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    4.0.1
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
    public function __construct(int $ttl = 0)
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
    public function getItemTtl(string $id): int
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
     * @param  ?int   $ttl
     * @return Session
     */
    public function saveItem(string $id, mixed $value, ?int $ttl = null): Session
    {
        $_SESSION['_POP_CACHE_'][$id] = serialize([
            'start' => time(),
            'ttl'   => ($ttl !== null) ? $ttl : $this->ttl,
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
    public function getItem(string $id): mixed
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
     * @return bool
     */
    public function hasItem(string $id): bool
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
    public function deleteItem(string $id): Session
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
    public function clear(): Session
    {
        $_SESSION['_POP_CACHE_'] = [];
        return $this;
    }

    /**
     * Destroy cache resource
     *
     * @return Session
     */
    public function destroy(): Session
    {
        $_SESSION = null;
        session_unset();
        session_destroy();
        return $this;
    }
}
