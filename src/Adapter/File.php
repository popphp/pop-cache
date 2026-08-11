<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Cache\Adapter;

use Pop\Cache\Clock;

/**
 * File adapter cache class
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.0.0
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
     * @param  Clock\ClockInterface $clock
     */
    public function __construct(string $dir, int $ttl = 0, Clock\ClockInterface $clock = new Clock\SystemClock())
    {
        parent::__construct($ttl, $clock);
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
     * @param  int    $default
     * @return int
     */
    public function getItemTtl(string $id, int $default = 0): int
    {
        $fileId = $this->fileId($id);
        $ttl    = $default;

        if (file_exists($fileId)) {
            $cacheValue = unserialize(file_get_contents($fileId), ['allowed_classes' => false]);
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
     * @throws Exception
     * @return File
     */
    public function saveItem(string $id, mixed $value, ?int $ttl = null): File
    {
        $fileId   = $this->fileId($id);
        $shardDir = dirname($fileId);

        if (!is_dir($shardDir) && !@mkdir($shardDir, 0777, true) && !is_dir($shardDir)) {
            throw new Exception('Error: Unable to create the cache shard directory.');
        }

        $tmpFile = $shardDir . DIRECTORY_SEPARATOR . uniqid('.tmp-', true);

        file_put_contents($tmpFile, serialize([
            'start' => $this->clock->now(),
            'ttl'   => ($ttl !== null) ? $ttl : $this->ttl,
            'value' => $value
        ]));
        rename($tmpFile, $fileId);

        return $this;
    }

    /**
     * Get an item from cache
     *
     * @param  string $id
     * @param  mixed  $default
     * @return mixed
     */
    public function getItem(string $id, mixed $default = false): mixed
    {
        $fileId = $this->fileId($id);
        $value  = $default;

        if (file_exists($fileId)) {
            $cacheValue = unserialize(file_get_contents($fileId), ['allowed_classes' => false]);
            if (($cacheValue['ttl'] == 0) || (($this->clock->now() - $cacheValue['start']) <= $cacheValue['ttl'])) {
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
        $fileId = $this->fileId($id);
        $result = false;

        if (file_exists($fileId)) {
            $cacheValue = unserialize(file_get_contents($fileId), ['allowed_classes' => false]);
            $result     = (($cacheValue['ttl'] == 0) || (($this->clock->now() - $cacheValue['start']) <= $cacheValue['ttl']));
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
        $fileId = $this->fileId($id);
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
            if (($obj == '.') || ($obj == '..')) {
                continue;
            }

            $path = $this->dir . DIRECTORY_SEPARATOR . $obj;

            if (is_dir($path) && preg_match('/^[0-9a-f]{2}$/', $obj)) {
                $this->clearShard($path);
            } else if (is_file($path)) {
                unlink($path);
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

    /**
     * Atomically increment a counter in cache, creating it at $initial if it doesn't exist
     *
     * Non-atomic read-modify-write through the same start/ttl/value envelope used by saveItem()/getItem() —
     * File has no native atomic primitive, so a counter here is an ordinary cached integer, fully
     * interoperable with getItem()/hasItem()/deleteItem().
     *
     * @param  string $id
     * @param  int    $amount
     * @param  int    $initial
     * @param  ?int   $ttl
     * @throws Exception
     * @return int
     */
    public function incrementItem(string $id, int $amount = 1, int $initial = 0, ?int $ttl = null): int
    {
        $current = $this->getItem($id, $initial);

        if (!is_int($current)) {
            throw new Exception('Error: The value at that key is not numeric.');
        }

        $value = $current + $amount;
        $this->saveItem($id, $value, $ttl);

        return $value;
    }

    /**
     * Atomically decrement a counter in cache, creating it at $initial if it doesn't exist
     *
     * Non-atomic read-modify-write through the same start/ttl/value envelope used by saveItem()/getItem() —
     * File has no native atomic primitive, so a counter here is an ordinary cached integer, fully
     * interoperable with getItem()/hasItem()/deleteItem().
     *
     * @param  string $id
     * @param  int    $amount
     * @param  int    $initial
     * @param  ?int   $ttl
     * @throws Exception
     * @return int
     */
    public function decrementItem(string $id, int $amount = 1, int $initial = 0, ?int $ttl = null): int
    {
        $current = $this->getItem($id, $initial);

        if (!is_int($current)) {
            throw new Exception('Error: The value at that key is not numeric.');
        }

        $value = $current - $amount;
        $this->saveItem($id, $value, $ttl);

        return $value;
    }

    /**
     * Clear all files in a shard directory and remove the now-empty directory
     *
     * @param  string $shardDir
     * @return void
     */
    protected function clearShard(string $shardDir): void
    {
        if (!$dh = @opendir($shardDir)) {
            return;
        }

        while (false !== ($obj = readdir($dh))) {
            if (($obj != '.') && ($obj != '..') && is_file($shardDir . DIRECTORY_SEPARATOR . $obj)) {
                unlink($shardDir . DIRECTORY_SEPARATOR . $obj);
            }
        }

        closedir($dh);
        @rmdir($shardDir);
    }

    /**
     * Build the sharded storage path for an item id
     *
     * @param  string $id
     * @return string
     */
    protected function fileId(string $id): string
    {
        $hash = sha1($id);
        return $this->dir . DIRECTORY_SEPARATOR . substr($hash, 0, 2) . DIRECTORY_SEPARATOR . $hash;
    }

}
