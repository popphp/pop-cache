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
 * Memcached cache adapter class
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    4.0.0
 */
class Memcached extends AbstractAdapter
{

    /**
     * Memcached object
     * @var ?\Memcached
     */
    protected ?\Memcached $memcached = null;

    /**
     * Memcached version
     * @var ?string
     */
    protected ?string $version = null;

    /**
     * Constructor
     *
     * Instantiate the memcached cache object
     *
     * @param  int    $ttl
     * @param  string $host
     * @param  int    $port
     * @param  int    $weight
     * @throws Exception
     */
    public function __construct(int $ttl = 0, string $host = 'localhost', int $port = 11211, int $weight = 1)
    {
        parent::__construct($ttl);
        if (!class_exists('Memcached', false)) {
            throw new Exception('Error: Memcached is not available.');
        }

        $this->memcached = new \Memcached();
        $this->addServer($host, $port, $weight);

        $version = $this->memcached->getVersion();
        if (isset($version[$host . ':' . $port])) {
            $this->version = $version[$host . ':' . $port];
        }
    }

    /**
     * Get the memcached object.
     *
     * @return \Memcached
     */
    public function memcached(): \Memcached
    {
        return $this->memcached;
    }

    /**
     * Get the current version of memcached.
     *
     * @param  string $host
     * @param  int    $port
     * @param  int    $weight
     * @return Memcached
     */
    public function addServer(string $host, int $port = 11211, int $weight = 1): Memcached
    {
        $this->memcached->addServer($host, $port, $weight);
        return $this;
    }

    /**
     * Get the current version of memcached.
     *
     * @param  array $servers
     * @return Memcached
     */
    public function addServers(array $servers): Memcached
    {
        $this->memcached->addServers($servers);
        return $this;
    }

    /**
     * Get the current version of memcached.
     *
     * @return ?string
     */
    public function getVersion(): ?string
    {
        return $this->version;
    }

    /**
     * Get the time-to-live for an item in cache
     *
     * @param  string $id
     * @return int
     */
    public function getItemTtl(string $id): int
    {
        $cacheValue = $this->memcached->get($id);
        $ttl        = false;

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
     * @param  ?int   $ttl
     * @return Memcached
     */
    public function saveItem(string $id, mixed $value, ?int $ttl = null): Memcached
    {
        $cacheValue = [
            'start' => time(),
            'ttl'   => ($ttl !== null) ? $ttl : $this->ttl,
            'value' => $value
        ];

        $this->memcached->set($id, $cacheValue, $cacheValue['ttl']);

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
        $cacheValue = $this->memcached->get($id);
        $value      = false;

        if (($cacheValue !== false) &&
            (($cacheValue['ttl'] == 0) || ((time() - $cacheValue['start']) <= $cacheValue['ttl']))) {
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
     * @return bool
     */
    public function hasItem(string $id): bool
    {
        return ($this->getItem($id) !== false);
    }

    /**
     * Delete a value in cache
     *
     * @param  string $id
     * @return Memcached
     */
    public function deleteItem(string $id): Memcached
    {
        $this->memcached->delete($id);
        return $this;
    }

    /**
     * Clear all stored values from cache
     *
     * @return Memcached
     */
    public function clear(): Memcached
    {
        $this->memcached->flush();
        return $this;
    }

    /**
     * Destroy cache resource
     *
     * @return Memcached
     */
    public function destroy(): Memcached
    {
        $this->memcached->flush();
        $this->memcached = null;
        return $this;
    }

}
