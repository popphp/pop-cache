pop-cache
=========

[![Build Status](https://travis-ci.org/popphp/pop-cache.svg?branch=master)](https://travis-ci.org/popphp/pop-cache)
[![Coverage Status](http://cc.popphp.org/coverage.php?comp=pop-cache)](http://cc.popphp.org/pop-cache/)

OVERVIEW
--------
`pop-cache` provides the ability to cache frequently accessed content via several different adapters.
Depending on the server environment and what's available, an application can use one of the following
cache adapters:

* Apc (cache service)
* File (directory on disk)
* Memcache (cache service)
* Memcached (cache service)
* Redis (cache service)
* Session (short-term caching in session)
* Sqlite (database file on disk)

`pop-cache` is a component of the [Pop PHP Framework](http://www.popphp.org/).

PHP 7
-----

While this component has been updated and tested to work with PHP 7, please note:

- Due to the unavailability or instability of the **apc/apcu/apc_bc** extensions, the APC class adapter may not function properly in PHP 7.
- Due to the unavailability or instability of the **memcache/memcached** extensions, the Memcache & Memcached class adapter may not function properly in PHP 7.

INSTALL
-------

Install `pop-cache` using Composer.

    composer require popphp/pop-cache

BASIC USAGE
-----------

### Setting up the different cache object adapters

```php
use Pop\Cache\Cache;
use Pop\Cache\Adapter;

// Using the apc adapter object, with a 5 minute lifetime
$apc = new Cache(new Adapter\Apc(300));

// Using the file adapter object, with a 5 minute lifetime
$file = new Cache(new Adapter\File('/path/to/my/cache/dir', 300));

// Using the memcached adapter object, with a 5 minute lifetime
$memcache = new Cache(new Adapter\Memcache(300));

// Using the redis adapter object, with a 5 minute lifetime
$redis = new Cache(new Adapter\Redis(300));

// Using the session adapter object, with a 5 minute lifetime
$session = new Cache(new Adapter\Session(300));

// Using the file adapter object, with a 5 minute lifetime
$sqlite = new Cache(new Adapter\Sqlite('/path/to/my/.htcachedb.sqlite', 300));

```

### Saving and recalling data from cache

Once a cache object is created, you can simply save and load data from it like below:

```php
// Save some data to the cache
$cache->save('foo', $myData);

// Recall that data later in the app.
// Returns false is the data does not exist or has expired.
$foo = $cache->load('foo');
```

### Deleting data from cache

```php
$cache->remove('foo');
```

### Clearing all data from cache

```php
$cache->clear();
```
