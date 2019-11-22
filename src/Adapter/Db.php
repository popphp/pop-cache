<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Cache\Adapter;

use Pop\Db\Adapter;

/**
 * Database cache adapter class
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.3.0
 */
class Db extends AbstractAdapter
{

    /**
     * Database adapter
     * @var Adapter\AbstractAdapter
     */
    protected $db = null;

    /**
     * Cache db table
     * @var string
     */
    protected $table = 'pop_cache';

    /**
     * Constructor
     *
     * Instantiate the DB writer object
     *
     * The DB table requires the following fields at a minimum:

     *     id    INT
     *     key   VARCHAR
     *     start INT
     *     ttl   INT
     *     value TEXT, VARCHAR, etc.
     *
     * @param  Adapter\AbstractAdapter $db
     * @param  int                     $ttl
     * @param  string                  $table
     */
    public function __construct(Adapter\AbstractAdapter $db, $ttl = 0, $table = 'pop_cache')
    {
        parent::__construct($ttl);

        $this->setDb($db);
        $this->setTable($table);

        if (!$db->hasTable($this->table)) {
            $this->createTable();
        }
    }

    /**
     * Set the current cache db adapter.
     *
     * @param  string $db
     * @return Db
     */
    public function setDb($db)
    {
        $this->db = $db;
        return $this;
    }

    /**
     * Get the current cache db adapter.
     *
     * @return Adapter\AbstractAdapter
     */
    public function getDb()
    {
        return $this->db;
    }

    /**
     * Get the current cache db table.
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Get the time-to-live for an item in cache
     *
     * @param  string $id
     * @return int
     */
    public function getItemTtl($id)
    {
        $sql         = $this->db->createSql();
        $placeholder = $sql->getPlaceholder();

        if ($placeholder == ':') {
            $placeholder .= 'key';
        } else if ($placeholder == '$') {
            $placeholder .= '1';
        }

        $sql->select()->from($this->table)->where('key = ' . $placeholder);

        $this->db->prepare($sql)
            ->bindParams(['key' => sha1($id)])
            ->execute();

        $rows = $this->db->fetchAll();

        return (isset($rows[0]) && isset($rows[0]['ttl'])) ? $rows[0]['ttl'] : 0;
    }

    /**
     * Save an item to cache
     *
     * @param  string $id
     * @param  mixed  $value
     * @param  int    $ttl
     * @return Db
     */
    public function saveItem($id, $value, $ttl = null)
    {
        // Determine if the value already exists.
        $sql         = $this->db->createSql();
        $placeholder = $sql->getPlaceholder();

        if ($placeholder == ':') {
            $placeholder .= 'key';
        } else if ($placeholder == '$') {
            $placeholder .= '1';
        }

        $sql->select()->from($this->table)->where('key = ' . $placeholder);

        $this->db->prepare($sql)
            ->bindParams(['key' => sha1($id)])
            ->execute();

        $rows = $this->db->fetchAll();

        $sql->reset();
        $placeholder = $sql->getPlaceholder();

        // If the value doesn't exist, save the new value.
        if (count($rows) == 0) {
            if ($placeholder == ':') {
                $placeholders = [':key', ':start', ':ttl', ':value'];
            } else if ($placeholder == '$') {
                $placeholders = ['$1', '$2', '$3', '$4'];
            } else {
                $placeholders = ['?', '?', '?', '?'];
            }
            $sql->insert($this->table)->values([
                'key'   => $placeholders[0],
                'start' => $placeholders[1],
                'ttl'   => $placeholders[2],
                'value' => $placeholders[3]
            ]);
            $params = [
                'key'   => sha1($id),
                'start' => time(),
                'ttl'   => (null !== $ttl) ? (int)$ttl : $this->ttl,
                'value' => serialize($value)
            ];
        // Else, update it.
        } else {
            if ($placeholder == ':') {
                $placeholders = [':start', ':ttl', ':value', ':key'];
            } else if ($placeholder == '$') {
                $placeholders = ['$1', '$2', '$3', '$4'];
            } else {
                $placeholders = ['?', '?', '?', '?'];
            }
            $sql->update($this->table)->values([
                'start' => $placeholders[0],
                'ttl'   => $placeholders[1],
                'value' => $placeholders[2]
            ])->where('key = ' . $placeholders[3]);
            $params = [
                'start' => time(),
                'ttl'   => (null !== $ttl) ? (int)$ttl : $this->ttl,
                'value' => serialize($value),
                'key'   => sha1($id)
            ];
        }

        // Save value
        $this->db->prepare($sql)
            ->bindParams($params)
            ->execute();

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
        $sql         = $this->db->createSql();
        $placeholder = $sql->getPlaceholder();
        $value       = false;

        if ($placeholder == ':') {
            $placeholder .= 'key';
        } else if ($placeholder == '$') {
            $placeholder .= '1';
        }

        $sql->select()->from($this->table)->where('key = ' . $placeholder);

        $this->db->prepare($sql)
            ->bindParams(['key' => sha1($id)])
            ->execute();

        $rows = $this->db->fetchAll();

        // If the value is found, check expiration and return.
        if (count($rows) > 0) {
            $cacheValue = $rows[0];
            if (($cacheValue['ttl'] == 0) || ((time() - $cacheValue['start']) <= $cacheValue['ttl'])) {
                $value = unserialize($cacheValue['value']);
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
        $sql         = $this->db->createSql();
        $placeholder = $sql->getPlaceholder();
        $result      = false;

        if ($placeholder == ':') {
            $placeholder .= 'key';
        } else if ($placeholder == '$') {
            $placeholder .= '1';
        }

        $sql->select()->from($this->table)->where('key = ' . $placeholder);

        $this->db->prepare($sql)
            ->bindParams(['key' => sha1($id)])
            ->execute();

        $rows = $this->db->fetchAll();

        // If the value is found, check expiration and return.
        if (count($rows) > 0) {
            $cacheValue = $rows[0];
            $result = (($cacheValue['ttl'] == 0) || ((time() - $cacheValue['start']) <= $cacheValue['ttl']));
        }

        return $result;
    }

    /**
     * Delete a value in cache
     *
     * @param  string $id
     * @return Db
     */
    public function deleteItem($id)
    {
        $sql         = $this->db->createSql();
        $placeholder = $sql->getPlaceholder();

        if ($placeholder == ':') {
            $placeholder .= 'key';
        } else if ($placeholder == '$') {
            $placeholder .= '1';
        }

        $sql->delete($this->table)->where('key = ' . $placeholder);

        $this->db->prepare($sql)
            ->bindParams(['key' => sha1($id)])
            ->execute();

        return $this;
    }

    /**
     * Clear all stored values from cache
     *
     * @return Db
     */
    public function clear()
    {
        $sql = $this->db->createSql();
        $sql->delete($this->table);
        $this->db->query($sql);
        return $this;
    }

    /**
     * Destroy cache resource
     *
     * @return Db
     */
    public function destroy()
    {
        $this->clear();
        return $this;
    }

    /**
     * Set the cache db table
     *
     * @param  string $table
     * @return Db
     */
    public function setTable($table)
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Create table in database
     *
     * @return void
     */
    protected function createTable()
    {
        $schema = $this->db->createSchema();
        $schema->create($this->table)
            ->int('id')->increment()
            ->varchar('key', 255)
            ->int('start')
            ->int('ttl')
            ->text('value')
            ->primary('id');

        $this->db->query($schema);
    }
}
