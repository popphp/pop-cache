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

$apc       = new Adapter\Apc(300);
$file      = new Adapter\File('/path/to/my/cache/dir', 300);
$memcache  = new Adapter\Memcache(300);
$memcached = new Adapter\Memcached(300);
$redis     = new Adapter\Redis(300);
$session   = new Adapter\Session(300);
$sqlite    = new Adapter\Sqlite('/path/to/my/.htcachedb.sqlite', 300);

// Then inject one of the adapters into the main cache object
$cache = new Cache($file);

```

### Saving and recalling data from cache

Once a cache object is created, you can simply save and load data from it like below:

```php
// Save some data to the cache
$cache->saveItem('foo', $myData);

// Recall that data later in the app.
// Returns false is the data does not exist or has expired.
$foo = $cache->getItem('foo');
```

### Deleting data from cache

```php
$cache->deleteItem('foo');
```

### Clearing all data from cache

```php
$cache->clear();
```
