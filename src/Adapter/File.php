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
 * File adapter cache class
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    4.0.2
 */
class File extends AbstractAdapter
{

    /**
     * Cache dir
     * @var ?string
     */
    protected ?string $dir = null;

    /**
     * Constructor
     *
     * Instantiate the cache file object
     *
     * @param  string $dir
     * @param  int    $ttl
     */
    public function __construct(string $dir, int $ttl = 0)
    {
        parent::__construct($ttl);
        $this->setDir($dir);
    }

    /**
     * Set the current cache dir
     *
     * @param  string $dir
     * @throws Exception
     * @return File
     */
    public function setDir(string $dir): File
    {
        if (!file_exists($dir)) {
            throw new Exception('Error: That cache directory does not exist.');
        } else if (!is_writable($dir)) {
            throw new Exception('Error: That cache directory is not writable.');
        }

        $this->dir = realpath($dir);

        return $this;
    }

    /**
     * Get the current cache dir
     *
     * @return ?string
     */
    public function getDir(): ?string
    {
        return $this->dir;
    }

    /**
     * Get the time-to-live for an item in cache
     *
     * @param  string $id
     * @return int
     */
    public function getItemTtl(string $id): int
    {
        $fileId = $this->dir . DIRECTORY_SEPARATOR . sha1($id);
        $ttl    = 0;

        if (file_exists($fileId)) {
            $cacheValue = unserialize(file_get_contents($fileId));
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
     * @return File
     */
    public function saveItem(string $id, mixed $value, ?int $ttl = null): File
    {
        file_put_contents($this->dir . DIRECTORY_SEPARATOR . sha1($id), serialize([
            'start' => time(),
            'ttl'   => ($ttl !== null) ? $ttl : $this->ttl,
            'value' => $value
        ]));

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
        $fileId = $this->dir . DIRECTORY_SEPARATOR . sha1($id);
        $value  = false;

        if (file_exists($fileId)) {
            $cacheValue = unserialize(file_get_contents($fileId));
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
        $fileId = $this->dir . DIRECTORY_SEPARATOR . sha1($id);
        $result = false;

        if (file_exists($fileId)) {
            $cacheValue = unserialize(file_get_contents($fileId));
            $result     = (($cacheValue['ttl'] == 0) || ((time() - $cacheValue['start']) <= $cacheValue['ttl']));
        }

        return $result;
    }

    /**
     * Delete a value in cache
     *
     * @param  string $id
     * @return File
     */
    public function deleteItem(string $id): File
    {
        $fileId = $this->dir . DIRECTORY_SEPARATOR . sha1($id);
        if (file_exists($fileId)) {
            unlink($fileId);
        }
        return $this;
    }

    /**
     * Clear all stored values from cache
     *
     * @return File
     */
    public function clear(): File
    {
        if (!$dh = @opendir($this->dir)) {
            return $this;
        }

        while (false !== ($obj = readdir($dh))) {
            if (($obj != '.') && ($obj != '..') &&
                !is_dir($this->dir . DIRECTORY_SEPARATOR . $obj) && is_file($this->dir . DIRECTORY_SEPARATOR . $obj)) {
                unlink($this->dir . DIRECTORY_SEPARATOR . $obj);
            }
        }

        closedir($dh);

        return $this;
    }

    /**
     * Destroy cache resource
     *
     * @return File
     */
    public function destroy(): File
    {
        $this->clear();
        @rmdir($this->dir);

        return $this;
    }

}
