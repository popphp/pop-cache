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
 * File adapter cache class
 *
 * @category   Pop
 * @package    Pop_Cache
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2015 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.1
 */
class File extends AbstractAdapter
{

    /**
     * Cache dir
     * @var string
     */
    protected $dir = null;

    /**
     * Constructor
     *
     * Instantiate the cache file object
     *
     * @param  string $dir
     * @param  int    $lifetime
     * @return File
     */
    public function __construct($dir, $lifetime = 0)
    {
        parent::__construct($lifetime);
        $this->setDir($dir);
    }

    /**
     * Set the current cache dir.
     *
     * @param  string $dir
     * @throws Exception
     * @return File
     */
    public function setDir($dir)
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
     * Get the current cache dir.
     *
     * @return string
     */
    public function getDir()
    {
        return $this->dir;
    }

    /**
     * Save a value to cache.
     *
     * @param  string $id
     * @param  mixed  $value
     * @return File
     */
    public function save($id, $value)
    {
        $file = $this->dir . DIRECTORY_SEPARATOR . sha1($id);

        $cacheValue = [
            'start'    => time(),
            'expire'   => ($this->lifetime != 0) ? time() + $this->lifetime : 0,
            'lifetime' => $this->lifetime,
            'value'    => $value
        ];
        file_put_contents($file, serialize($cacheValue));

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
        $fileId = $this->dir . DIRECTORY_SEPARATOR . sha1($id);
        $value  = false;

        if (file_exists($fileId)) {
            $cacheValue = unserialize(file_get_contents($fileId));
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
     * @return File
     */
    public function remove($id)
    {
        $fileId = $this->dir . DIRECTORY_SEPARATOR . sha1($id);
        if (file_exists($fileId)) {
            unlink($fileId);
        }

        return $this;
    }

    /**
     * Clear all stored values from cache.
     *
     * @param  boolean $del
     * @param  string  $path
     * @return File
     */
    public function clear($del = false, $path = null)
    {
        if (null === $path) {
            $path = $this->dir;
        }

        // Get a directory handle.
        if (!$dh = @opendir($path)) {
            return;
        }

        // Recursively dig through the directory, deleting files where applicable.
        while (false !== ($obj = readdir($dh))) {
            if ($obj == '.' || $obj == '..') {
                continue;
            }
            if (!@unlink($path . DIRECTORY_SEPARATOR . $obj)) {
                $this->clear(true, $path . DIRECTORY_SEPARATOR . $obj);
            }
        }

        // Close the directory handle.
        closedir($dh);

        // If the delete flag was passed, remove the top level directory.
        if ($del) {
            $this->delete($path);
        }

        return $this;
    }

    /**
     * Method to delete the top level directory
     *
     * @param  string  $path
     * @return File
     */
    public function delete($path = null)
    {
        if (null === $path) {
            $path = $this->dir;
        }
        @rmdir($path);

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
        $fileId = $this->dir . DIRECTORY_SEPARATOR . sha1($id);
        $value  = 0;

        if (file_exists($fileId)) {
            $cacheValue = unserialize(file_get_contents($fileId));
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
        $fileId = $this->dir . DIRECTORY_SEPARATOR . sha1($id);
        $value  = 0;

        if (file_exists($fileId)) {
            $cacheValue = unserialize(file_get_contents($fileId));
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
        $fileId = $this->dir . DIRECTORY_SEPARATOR . sha1($id);
        $value  = 0;

        if (file_exists($fileId)) {
            $cacheValue = unserialize(file_get_contents($fileId));
            $value      = $cacheValue['lifetime'];
        }

        return $value;
    }

}
