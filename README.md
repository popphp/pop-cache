pop-cache
=========

[![Build Status](https://travis-ci.org/popphp/pop-cache.svg?branch=master)](https://travis-ci.org/popphp/pop-cache)
[![Coverage Status](http://www.popphp.org/cc/coverage.php?comp=pop-cache)](http://www.popphp.org/cc/pop-cache/)

OVERVIEW
--------
`pop-cache` provides the ability to cache frequently accessed content via several different adapters.
Depending on the server environment and what's available, an application can use one of the following
cache adapters:

* File (directory on disk)
* Sqlite (database file on disk)
* Apc (cache service in memory)
* Memcached (cache service in memory)

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

// Using the file adapter object, with a 5 minute lifetime
$fileCache = new Cache(new Adapter\File('/path/to/my/cache/dir'), 300);

// Using the file adapter object, with a 5 minute lifetime
$dbCache = new Cache(new Adapter\Sqlite('/path/to/my/.htcachedb.sqlite'), 300);

// Using the apc adapter object, with a 5 minute lifetime
$apcCache = new Cache(new Adapter\Apc(), 300);

// Using the memcached adapter object, with a 5 minute lifetime
$memCache = new Cache(new Adapter\Memcached(), 300);
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
