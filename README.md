pop-cache
=========

[![Build Status](https://github.com/popphp/pop-cache/workflows/phpunit/badge.svg)](https://github.com/popphp/pop-cache/actions)
[![Coverage Status](http://cc.popphp.org/coverage.php?comp=pop-cache)](http://cc.popphp.org/pop-cache/)

[![Join the chat at https://popphp.slack.com](https://media.popphp.org/img/slack.svg)](https://popphp.slack.com)
[![Join the chat at https://discord.gg/D9JBxPa5](https://media.popphp.org/img/discord.svg)](https://discord.gg/D9JBxPa5)

* [Overview](#overview)
* [Install](#install)
* [Quickstart](#quickstart)
* [APC](#apc)
* [Memcached](#memcached)
* [Redis](#redis)
* [File](#file)
* [Database](#database)
* [Session](#session)

Overview
--------
`pop-cache` provides the ability to cache frequently accessed content via several different adapters.
The adapters all share the same interface and are interchangeable. Depending on the server environment
and what's available, an application can use one of the following cache adapters:

* Apc (caching service)
* Memcached (caching service)
* Redis (caching service)
* File (directory on disk)
* Db (database caching)
* Session (short-term caching in session)

`pop-cache` is a component of the [Pop PHP Framework](http://www.popphp.org/).

[Top](#pop-cache)

Install
-------

Install `pop-cache` using Composer.

    composer require popphp/pop-cache

Or, require it in your composer.json file

    "require": {
        "popphp/pop-cache" : "^4.0.0"
    }

[Top](#pop-cache)

Quickstart
----------

Here is a basic example to create a cache object and then save and retrieve some data from it.
The adapter can be passed a "time-to-live" value in seconds (TTL). If set to `0`, then the cached
items will never expire:

```php
use Pop\Cache\Cache;
use Pop\Cache\Adapter\File;

// Passing the file adapter the location on disk and the TTL
$cache = new Cache(new Adapter\File('/path/to/my/cache/dir', 300));

$cache->saveItem('foo', $data);

$data = $cache->getItem('foo');
```

### Check if the cache has an item

```php
if $cache->hasItem('foo') { } // Return bool
```

### Delete item

```php
$cache->deleteItem('foo');
```

### Delete items

```php
$cache->deleteItems(['foo', 'bar']);
```

### Clear all items out of the cache

```php
$cache->clear();
```

[Top](#pop-cache)

APC
---

Using the APC adapter requires APC to be correctly set up in the environment.

```php
use Pop\Cache\Cache;
use Pop\Cache\Adapter\Apc;

$cache = new Cache(new Apc(300));
```

[Top](#pop-cache)

Memcached
---------

Using the Memcached adapter requires Memcached to be correctly set up in the environment.

```php
use Pop\Cache\Cache;
use Pop\Cache\Adapter\Memcached;

$cache = new Cache(new Memcached(300, 'localhost', 11211));
```

[Top](#pop-cache)

Redis
-----

Using the Redis adapter requires Redis to be correctly set up in the environment.

```php
use Pop\Cache\Cache;
use Pop\Cache\Adapter\Redis;

$cache = new Cache(new Redis(300, 'localhost', 6379));
```

[Top](#pop-cache)

File
----

Using the file adapter will simply store the cache data on the local disk.

```php
use Pop\Cache\Cache;
use Pop\Cache\Adapter\File;

$cache = new Cache(new Adapter\File('/path/to/my/cache/dir', 300));
```

[Top](#pop-cache)

Database
--------

Using the database adapter will require the database to be set up correctly and the use of
the `pop-db` component.

```php
use Pop\Cache\Cache;
use Pop\Cache\Adapter;
use Pop\Db\Db;

$cache = new Cache(new Adapter\Db(Db::sqliteConnect(['database' => __DIR__ . '/tmp/cache.sqlite']), 300));
```

[Top](#pop-cache)

Session
-------

Using the session adapter will store the cached data in session

```php
use Pop\Cache\Cache;
use Pop\Cache\Adapter\Session;

$cache = new Cache(new Session(300));
```

[Top](#pop-cache)
