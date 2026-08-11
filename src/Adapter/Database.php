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

use Pop\Db;
use Pop\Cache\Clock;

/**
 * Database cache adapter class
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.0.0
 */
class Database extends AbstractAdapter
{

    /**
     * Database adapter
     * @var ?Db\Adapter\AbstractAdapter
     */
    protected ?Db\Adapter\AbstractAdapter $db = null;

    /**
     * Cache db table
     * @var string
     */
    protected string $table = 'pop_cache';

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
     * @param  Db\Adapter\AbstractAdapter $db
     * @param  int                        $ttl
     * @param  string                     $table
     * @param  Clock\ClockInterface       $clock
     */
    public function __construct(
        Db\Adapter\AbstractAdapter $db, int $ttl = 0, string $table = 'pop_cache',
        Clock\ClockInterface $clock = new Clock\SystemClock()
    )
    {
        parent::__construct($ttl, $clock);

        $this->setDb($db);
        $this->setTable($table);

        if (!$db->hasTable($this->table)) {
            $this->createTable();
        }
    }

    /**
     * Set the current cache db adapter.
     *
     * @param  Db\Adapter\AbstractAdapter $db
     * @return Database
     */
    public function setDb(Db\Adapter\AbstractAdapter $db): Database
    {
        $this->db = $db;
        return $this;
    }

    /**
     * Get the current cache db adapter.
     *
     * @return Db\Adapter\AbstractAdapter
     */
    public function getDb(): Db\Adapter\AbstractAdapter
    {
        return $this->db;
    }

    /**
     * Get the current cache db table.
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
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

        return (isset($rows[0]) && isset($rows[0]['ttl'])) ? $rows[0]['ttl'] : $default;
    }

    /**
     * Save an item to cache
     *
     * @param  string $id
     * @param  mixed  $value
     * @param  ?int   $ttl
     * @return Database
     */
    public function saveItem(string $id, mixed $value, ?int $ttl = null): Database
    {
        $sql    = $this->db->createSql();
        $dbType = $sql->getDbType();
        $key    = sha1($id);
        $params = [
            'key'   => $key,
            'start' => $this->clock->now(),
            'ttl'   => ($ttl !== null) ? $ttl : $this->ttl,
            'value' => serialize($value)
        ];

        if (($dbType == Db\Sql::MYSQL) || ($dbType == Db\Sql::SQLITE) || ($dbType == Db\Sql::PGSQL)) {
            $placeholder = $sql->getPlaceholder();

            if ($placeholder == ':') {
                $placeholders = [':key', ':start', ':ttl', ':value'];
            } else if ($placeholder == '$') {
                $placeholders = ['$1', '$2', '$3', '$4'];
            } else {
                $placeholders = ['?', '?', '?', '?'];
            }

            $insert = $sql->insert($this->table)->values([
                'key'   => $placeholders[0],
                'start' => $placeholders[1],
                'ttl'   => $placeholders[2],
                'value' => $placeholders[3]
            ]);

            if ($dbType == Db\Sql::MYSQL) {
                $insert->onDuplicateKeyUpdate(['start', 'ttl', 'value']);
            } else {
                $insert->onConflict(['start', 'ttl', 'value'], 'key');
            }

            $this->db->prepare($sql)
                ->bindParams($params)
                ->execute();
        } else {
            // SQL Server: pop-db has no MERGE/upsert support for this driver, so fall back to
            // select-then-insert-or-update, narrowing (not eliminating) the race with a transaction.
            $this->db->transaction(function() use ($sql, $key, $params) {
                $placeholder        = $sql->getPlaceholder();
                $lookupPlaceholder  = ($placeholder == ':') ? ':key' : (($placeholder == '$') ? '$1' : '?');

                $sql->select()->from($this->table)->where('key = ' . $lookupPlaceholder);
                $this->db->prepare($sql)->bindParams(['key' => $key])->execute();
                $rows = $this->db->fetchAll();

                $sql->reset();
                $placeholder = $sql->getPlaceholder();

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
                    $this->db->prepare($sql)->bindParams($params)->execute();
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
                    $this->db->prepare($sql)->bindParams([
                        'start' => $params['start'],
                        'ttl'   => $params['ttl'],
                        'value' => $params['value'],
                        'key'   => $key
                    ])->execute();
                }
            });
        }

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
        $sql         = $this->db->createSql();
        $placeholder = $sql->getPlaceholder();
        $value       = $default;

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
            if (($cacheValue['ttl'] == 0) || (($this->clock->now() - $cacheValue['start']) <= $cacheValue['ttl'])) {
                $value = unserialize($cacheValue['value'], ['allowed_classes' => false]);
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
            $result = (($cacheValue['ttl'] == 0) || (($this->clock->now() - $cacheValue['start']) <= $cacheValue['ttl']));
        }

        return $result;
    }

    /**
     * Delete a value in cache
     *
     * @param  string $id
     * @return Database
     */
    public function deleteItem(string $id): Database
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
     * @return Database
     */
    public function clear(): Database
    {
        $sql = $this->db->createSql();
        $sql->delete($this->table);
        $this->db->query($sql);

        return $this;
    }

    /**
     * Destroy cache resource
     *
     * @return Database
     */
    public function destroy(): Database
    {
        $this->clear();
        return $this;
    }

    /**
     * Atomically increment a counter in cache, creating it at $initial if it doesn't exist
     *
     * Non-atomic read-modify-write through the same start/ttl/value envelope used by saveItem()/getItem() —
     * Database has no native atomic primitive, so a counter here is an ordinary cached integer, fully
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
     * Database has no native atomic primitive, so a counter here is an ordinary cached integer, fully
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
     * Set the cache db table
     *
     * @param  string $table
     * @return Database
     */
    public function setTable(string $table): Database
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Create table in database
     *
     * @return void
     */
    protected function createTable(): void
    {
        $schema = $this->db->createSchema();
        $schema->create($this->table)
            ->int('id')->increment()
            ->varchar('key', 255)
            ->int('start')
            ->int('ttl')
            ->text('value')
            ->primary('id')
            ->unique('key', $this->table . '_key_unique');

        $schema->execute();
    }
}
