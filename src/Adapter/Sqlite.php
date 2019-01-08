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
 * SQLite cache adapter class
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.3
 */
class Sqlite extends AbstractAdapter
{

    /**
     * Cache db file
     * @var string
     */
    protected $db = null;

    /**
     * Cache db table
     * @var string
     */
    protected $table = 'pop_cache';

    /**
     * Sqlite DB object
     * @var \PDO|\SQLite3
     */
    protected $sqlite = null;

    /**
     * Sqlite DB statement object (either a PDOStatement or SQLite3Stmt object)
     * @var mixed
     */
    protected $statement = null;

    /**
     * Database results
     * @var resource
     */
    protected $result;

    /**
     * PDO flag
     * @var boolean
     */
    protected $isPdo = false;

    /**
     * Constructor
     *
     * Instantiate the cache db object
     *
     * @param  string  $db
     * @param  int     $ttl
     * @param  string  $table
     * @param  boolean $pdo
     * @throws Exception
     */
    public function __construct($db, $ttl = 0, $table = 'pop_cache', $pdo = false)
    {
        parent::__construct($ttl);

        $this->setDb($db);

        $pdoDrivers = (class_exists('Pdo', false)) ? \PDO::getAvailableDrivers() : [];
        if (!class_exists('Sqlite3', false) && !in_array('sqlite', $pdoDrivers)) {
            throw new Exception('Error: SQLite is not available.');
        } else if (($pdo) && !in_array('sqlite', $pdoDrivers)) {
            $pdo = false;
        } else if ((!$pdo) && !class_exists('Sqlite3', false)) {
            $pdo = true;
        }

        if ($pdo) {
            $this->sqlite = new \PDO('sqlite:' . $this->db);
            $this->isPdo  = true;
        } else {
            $this->sqlite = new \SQLite3($this->db);
        }

        if (null !== $table) {
            $this->setTable($table);
        }
    }

    /**
     * Set the current cache db file.
     *
     * @param  string $db
     * @throws Exception
     * @return Sqlite
     */
    public function setDb($db)
    {
        $this->db = $db;
        $dir      = dirname($this->db);

        // If the database file doesn't exist, create it.
        if (!file_exists($this->db)) {
            if (is_writable($dir)) {
                touch($db);
            } else {
                throw new Exception('Error: That cache db file and/or directory is not writable.');
            }
        }

        // Make it writable.
        chmod($this->db, 0777);

        // Check the permissions, access the database and check for the cache table.
        if (!is_writable($dir) || !is_writable($this->db)) {
            throw new Exception('Error: That cache db file and/or directory is not writable.');
        }

        if (!class_exists('Sqlite3', false) && !class_exists('Pdo', false)) {
            throw new Exception('Error: Neither SQLite3 or PDO are available.');
        }

        return $this;
    }

    /**
     * Get the current cache db file.
     *
     * @return string
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
        $ttl = 0;

        // Determine if the value already exists.
        $rows = [];

        $this->prepare('SELECT * FROM "' . $this->table . '" WHERE "id" = :id')
             ->bindParams(['id' => sha1($id)])
             ->execute();

        if ($this->isPdo) {
            $rows = $this->statement->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            while (($row = $this->result->fetchArray(SQLITE3_ASSOC)) != false) {
                $rows[] = $row;
            }
        }

        // If the value is found, check expiration and return.
        if (count($rows) > 0) {
            $cacheValue = $rows[0];
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
     * @return Sqlite
     */
    public function saveItem($id, $value, $ttl = null)
    {
        // Determine if the value already exists.
        $rows = [];

        $this->prepare('SELECT * FROM "' . $this->table . '" WHERE "id" = :id')
             ->bindParams(['id' => sha1($id)])
             ->execute();

        if ($this->isPdo) {
            $rows = $this->statement->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            while (($row = $this->result->fetchArray(SQLITE3_ASSOC)) != false) {
                $rows[] = $row;
            }
        }

        // If the value doesn't exist, save the new value.
        if (count($rows) == 0) {
            $sql = 'INSERT INTO "' . $this->table .
                '" ("id", "start", "ttl", "value") VALUES (:id, :start, :ttl, :value)';
            $params = [
                'id'    => sha1($id),
                'start' => time(),
                'ttl'   => (null !== $ttl) ? (int)$ttl : $this->ttl,
                'value' => serialize($value)
            ];
        // Else, update it.
        } else {
            $sql = 'UPDATE "' . $this->table .
                '" SET "start" = :start, "ttl" = :ttl, "value" = :value WHERE "id" = :id';
            $params = [
                'start' => time(),
                'ttl'   => (null !== $ttl) ? (int)$ttl : $this->ttl,
                'value' => serialize($value),
                'id'    => sha1($id)
            ];
        }

        // Save value
        $this->prepare($sql)
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
        $value = false;

        // Determine if the value already exists.
        $rows = [];

        $this->prepare('SELECT * FROM "' . $this->table . '" WHERE "id" = :id')
             ->bindParams(['id' => sha1($id)])
             ->execute();

        if ($this->isPdo) {
            $rows = $this->statement->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            while (($row = $this->result->fetchArray(SQLITE3_ASSOC)) != false) {
                $rows[] = $row;
            }
        }

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
        $result = false;

        // Determine if the value already exists.
        $rows = [];

        $this->prepare('SELECT * FROM "' . $this->table . '" WHERE "id" = :id')
             ->bindParams(['id' => sha1($id)])
             ->execute();

        if ($this->isPdo) {
            $rows = $this->statement->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            while (($row = $this->result->fetchArray(SQLITE3_ASSOC)) != false) {
                $rows[] = $row;
            }
        }

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
     * @return Sqlite
     */
    public function deleteItem($id)
    {
        $this->prepare('DELETE FROM "' . $this->table . '" WHERE "id" = :id')
             ->bindParams(['id' => sha1($id)])
             ->execute();

        return $this;
    }

    /**
     * Clear all stored values from cache
     *
     * @return Sqlite
     */
    public function clear()
    {
        $this->query('DELETE FROM "' . $this->table . '"');
        return $this;
    }

    /**
     * Destroy cache resource
     *
     * @return Sqlite
     */
    public function destroy()
    {
        $this->query('DELETE FROM "' . $this->table . '"');
        if (file_exists($this->db)) {
            unlink($this->db);
        }

        return $this;
    }

    /**
     * Set the cache db table
     *
     * @param  string $table
     * @return Sqlite
     */
    public function setTable($table)
    {
        $this->table = addslashes($table);
        $this->checkTable();
        return $this;
    }

    /**
     * Prepare a SQL query
     *
     * @param  string $sql
     * @return Sqlite
     */
    protected function prepare($sql)
    {
        $this->statement = $this->sqlite->prepare($sql);
        return $this;
    }

    /**
     * Bind parameters to for a prepared SQL query
     *
     * @param  array  $params
     * @return Sqlite
     */
    protected function bindParams($params)
    {
        foreach ($params as $dbColumnName => $dbColumnValue) {
            ${$dbColumnName} = $dbColumnValue;
            $this->statement->bindParam(':' . $dbColumnName, ${$dbColumnName});
        }

        return $this;
    }

    /**
     * Execute the prepared SQL query
     *
     * @throws Exception
     * @return void
     */
    protected function execute()
    {
        if (null === $this->statement) {
            throw new Exception('Error: The database statement resource is not currently set.');
        }

        $this->result = $this->statement->execute();
    }

    /**
     * Execute the SQL query
     *
     * @param  string $sql
     * @throws Exception
     * @return void
     */
    public function query($sql)
    {
        if ($this->isPdo) {
            $sth = $this->sqlite->prepare($sql);

            if (!($sth->execute())) {
                throw new Exception($sth->errorCode() . ': ' .  $sth->errorInfo());
            } else {
                $this->result = $sth;
            }
        } else {
            if (!($this->result = $this->sqlite->query($sql))) {
                throw new Exception('Error: ' . $this->sqlite->lastErrorCode() . ': ' . $this->sqlite->lastErrorMsg() . '.');
            }
        }
    }

    /**
     * Check if cache table exists
     *
     * @return void
     */
    protected function checkTable()
    {
        $tables = [];
        $sql    = "SELECT name FROM sqlite_master WHERE type IN ('table', 'view') AND name NOT LIKE 'sqlite_%' " .
            "UNION ALL SELECT name FROM sqlite_temp_master WHERE type IN ('table', 'view') ORDER BY 1";

        if ($this->isPdo) {
            $sth = $this->sqlite->prepare($sql);
            $sth->execute();
            $result = $sth;
            while (($row = $result->fetch(\PDO::FETCH_ASSOC)) != false) {
                $tables[] = $row['name'];
            }
        } else {
            $result = $this->sqlite->query($sql);
            while (($row = $result->fetchArray(SQLITE3_ASSOC)) != false) {
                $tables[] = $row['name'];
            }
        }

        // If the cache table doesn't exist, create it.
        if (!in_array($this->table, $tables)) {
            $sql = 'CREATE TABLE IF NOT EXISTS "' . $this->table .
                '" ("id" VARCHAR PRIMARY KEY NOT NULL UNIQUE, "start" INTEGER, "ttl" INTEGER, "value" BLOB, "time" INTEGER)';

            if ($this->isPdo) {
                $sth = $this->sqlite->prepare($sql);
                $sth->execute();
            } else {
                $this->sqlite->query($sql);
            }
        }
    }

}
