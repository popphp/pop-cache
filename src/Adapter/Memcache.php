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
 * Memcache cache adapter class
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.3
 */
class Memcache extends AbstractAdapter
{

    /**
     * Memcache object
     * @var \Memcache
     */
    protected $memcache = null;

    /**
     * Constructor
     *
     * Instantiate the memcache cache object
     *
     * @param  int    $ttl
     * @param  string $host
     * @param  int    $port
     * @throws Exception
     */
    public function __construct($ttl = 0, $host = 'localhost', $port = 11211)
    {
        parent::__construct($ttl);
        if (!class_exists('Memcache', false)) {
            throw new Exception('Error: Memcache is not available.');
        }

        $this->memcache = new \Memcache();
        if (!$this->memcache->connect($host, (int)$port)) {
            throw new Exception('Error: Unable to connect to the memcache server.');
        }
    }

    /**
     * Get the memcache object.
     *
     * @return \Memcache
     */
    public function memcache()
    {
        return $this->memcache;
    }

    /**
     * Get the current version of memcache.
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->memcache->getVersion();
    }

    /**
     * Get the time-to-live for an item in cache
     *
     * @param  string $id
     * @return int
     */
    public function getItemTtl($id)
    {
        $cacheValue = $this->memcache->get($id);
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
     * @return Memcache
     */
    public function saveItem($id, $value, $ttl = null)
    {
        $cacheValue = [
            'start' => time(),
            'ttl'   => (null !== $ttl) ? (int)$ttl : $this->ttl,
            'value' => $value
        ];

        $this->memcache->set($id, $cacheValue, false, $cacheValue['ttl']);
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
        $cacheValue = $this->memcache->get($id);
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
     * @return Memcache
     */
    public function deleteItem($id)
    {
        $this->memcache->delete($id);
        return $this;
    }

    /**
     * Clear all stored values from cache
     *
     * @return Memcache
     */
    public function clear()
    {
        $this->memcache->flush();
        return $this;
    }

    /**
     * Destroy cache resource
     *
     * @return Memcache
     */
    public function destroy()
    {
        $this->memcache->flush();
        $this->memcache = null;
        return $this;
    }

}
