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
 * APC cache adapter class
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    4.0.0
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
    public function __construct(int $ttl = 0)
    {
        parent::__construct($ttl);
        if (!function_exists('apcu_cache_info')) {
            throw new Exception('Error: APCu is not available.');
        }
    }

    /**
     * Method to get the current APC info.
     *
     * @return array
     */
    public function getInfo(): array
    {
        return apcu_cache_info();
    }

    /**
     * Get the time-to-live for an item in cache
     *
     * @param  string $id
     * @return int
     */
    public function getItemTtl(string $id): int
    {
        $cacheValue = apcu_fetch($id);
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
     * @param  ?int   $ttl
     * @return Apc
     */
    public function saveItem(string $id, mixed $value, ?int $ttl = null): Apc
    {
        $cacheValue = [
            'start' => time(),
            'ttl'   => ($ttl !== null) ? $ttl : $this->ttl,
            'value' => $value
        ];

        apcu_store($id, $cacheValue, $cacheValue['ttl']);

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
        $cacheValue = apcu_fetch($id);
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
     * @return Apc
     */
    public function deleteItem(string $id): Apc
    {
        apcu_delete($id);
        return $this;
    }

    /**
     * Clear all stored values from cache
     *
     * @return Apc
     */
    public function clear(): Apc
    {
        apcu_clear_cache();
        return $this;
    }

    /**
     * Destroy cache resource
     *
     * @return Apc
     */
    public function destroy(): Apc
    {
        $this->clear();
        return $this;
    }

}
