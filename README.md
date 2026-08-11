pop-cache
=========

[![Build Status](https://github.com/popphp/pop-cache/workflows/phpunit/badge.svg)](https://github.com/popphp/pop-cache/actions)
[![Coverage Status](http://cc.popphp.org/coverage.php?comp=pop-cache)](http://cc.popphp.org/pop-cache/)

[![Join the chat at https://discord.gg/TZjgT74U7E](https://media.popphp.org/img/discord.svg)](https://discord.gg/TZjgT74U7E)

* [Overview](#overview)
* [Install](#install)
* [Quickstart](#quickstart)
  * [Array and property syntax](#array-and-property-syntax)
  * [A note on caching objects](#a-note-on-caching-objects)
  * [Disambiguating a cache miss from a cached `false`](#disambiguating-a-cache-miss-from-a-cached-false)
  * [Injecting a clock for deterministic tests](#injecting-a-clock-for-deterministic-tests)
  * [PSR-16 (SimpleCache) compatibility](#psr-16-simplecache-compatibility)
  * [PSR-6 (CacheItemPoolInterface) compatibility](#psr-6-cacheitempoolinterface-compatibility)
  * [Get-or-compute-and-cache with `remember()`](#get-or-compute-and-cache-with-remember)
  * [Atomic counters with `incrementItem()`/`decrementItem()`](#atomic-counters-with-incrementitemdecrementitem)
  * [Bulk invalidation with tags](#bulk-invalidation-with-tags)
  * [Check if the cache has an item](#check-if-the-cache-has-an-item)
  * [Save items](#save-items)
  * [Delete item](#delete-item)
  * [Delete items](#delete-items)
  * [Clear all items out of the cache](#clear-all-items-out-of-the-cache)
  * [Destroy the cache resource](#destroy-the-cache-resource)
  * [Adapter introspection](#adapter-introspection)
* [APC](#apc)
* [Memcached](#memcached)
* [Redis](#redis)
* [File](#file)
* [Database](#database)
* [Session](#session)
* [Memory](#memory)
* [Null](#null)

Overview
--------
`pop-cache` provides the ability to cache frequently accessed content via several different adapters.
The adapters all share the same interface and are interchangeable. Depending on the server environment
and what's available, an application can use one of the following cache adapters:

* Apc (caching service)
* Memcached (caching service)
* Redis (caching service)
* File (directory on disk)
* Database (database caching)
* Session (short-term caching in session)
* Memory (in-memory array, for tests or single-request caching)
* NullAdapter (disables caching entirely, a no-op)

`Pop\Cache\Cache` also implements [PSR-16](https://www.php-fig.org/psr/psr-16/) (`Psr\SimpleCache\CacheInterface`) directly, so it's usable anywhere a PSR-16 cache is expected — see the "PSR-16 (SimpleCache) compatibility" section below.

`pop-cache` is a component of the [Pop PHP Framework](https://www.popphp.org/).

[Top](#pop-cache)

Install
-------

Install `pop-cache` using Composer.

    composer require popphp/pop-cache

Or, require it in your composer.json file

    "require": {
        "popphp/pop-cache" : "^5.0.0"
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
$cache = new Cache(new File('/path/to/my/cache/dir', 300));

$cache->saveItem('foo', $data);

$data = $cache->getItem('foo');
```

[Top](#pop-cache)

### Array and property syntax

`Cache` also implements `ArrayAccess` and magic `__get`/`__set`/`__isset`/`__unset` methods, as shorthand for
`getItem()`/`saveItem()`/`hasItem()`/`deleteItem()`:

```php
$cache['foo'] = $data;      // same as $cache->saveItem('foo', $data)
$data = $cache['foo'];      // same as $cache->getItem('foo')
isset($cache['foo']);       // same as $cache->hasItem('foo')
unset($cache['foo']);       // same as $cache->deleteItem('foo')

$cache->foo = $data;        // property syntax works the same way
$data = $cache->foo;
isset($cache->foo);
unset($cache->foo);
```

Both forms route through the same validated `getItem()`/`saveItem()`/`hasItem()`/`deleteItem()` methods, not
the adapter directly — a reserved-character key throws the same `InvalidArgumentException` whichever syntax
you use.

[Top](#pop-cache)

### A note on caching objects

The `File`, `Database`, `Redis`, and `Session` adapters deserialize cached values with PHP's `unserialize()`
hardened via `allowed_classes: false` (a standard mitigation against PHP Object Injection). If you cache a plain
PHP object, `getItem()` will return it back as an inert `__PHP_Incomplete_Class` instance rather than a live
instance of the original class. Scalars, strings, and arrays of scalars are unaffected — but any object nested
inside a cached array is also returned as `__PHP_Incomplete_Class`. Reading a property off one emits a warning
and yields `null`; calling a method on one is a fatal error. `Apc` and `Memcached` are not affected by this,
since they rely on their extensions' own internal serialization rather than PHP's `unserialize()`.

[Top](#pop-cache)

### Disambiguating a cache miss from a cached `false`

`getItem()` returns `false` both when an id isn't in the cache and when the cached value legitimately *is*
`false` — to tell these apart, pass a second `$default` argument (returned only on a genuine miss):

```php
$cache->saveItem('feature_enabled', false);

$cache->getItem('feature_enabled', null); // false — the real cached value
$cache->getItem('feature_disabled_key', null); // null — a genuine miss
```

`getItemTtl()` has the same optional second `$default` argument (default `0`), for the analogous case of
telling "not found" apart from "found, with a TTL of `0` (never expires)".

Because `getItemTtl()` returns `int`, its `$default` sentinel must also be an `int` — pick a value you know
you'll never legitimately store as a TTL. Note that `saveItem()` accepts a negative TTL (meaning "already
expired"), so `getItemTtl()` cannot distinguish "never cached" from "cached with an explicit TTL of `-1`" if
`-1` is used as the sentinel; when that ambiguity matters, call `hasItem()` first for an unambiguous existence
check.

Widening `getItem()`/`getItemTtl()` to accept this optional `$default` parameter is fully backward compatible
for code that *calls* this library — omitting the argument preserves prior behavior everywhere. It is,
however, a breaking change for any third-party class implementing `AdapterInterface` (or extending
`AbstractAdapter`) with the old, narrower method signatures; such classes need the matching optional
`$default` parameters added to their own `getItem()`/`getItemTtl()` implementations to remain compatible.

[Top](#pop-cache)

### Injecting a clock for deterministic tests

Every adapter's constructor accepts an optional trailing `$clock` parameter — an instance of
`Pop\Cache\Clock\ClockInterface` — used to resolve "now" instead of calling PHP's `time()` directly. It defaults
to `Pop\Cache\Clock\SystemClock`, which just wraps `time()`, so nothing changes unless you pass your own.

For deterministic tests of TTL/expiration behavior, inject `Pop\Cache\Clock\MutableClock` instead of relying on
real `sleep()` calls:

```php
use Pop\Cache\Cache;
use Pop\Cache\Adapter\File;
use Pop\Cache\Clock\MutableClock;

$clock = new MutableClock();
$cache = new Cache(new File('/path/to/my/cache/dir', 0, $clock));

$cache->saveItem('foo', 'bar', 10); // TTL of 10 seconds
$clock->advance(11);

$cache->getItem('foo'); // false — expired, instantly and exactly, no sleep() needed
```

Alternatively, pin an absolute timestamp instead of advancing incrementally:

```php
$clock->setTime(2000); // Set clock to a fixed Unix timestamp
```

**Important:** For adapters backed by external services with their own TTL support — `Apc`, `Memcached`, and `Redis` — the injected clock only controls this library's expiration-envelope check; the backing service's native TTL eviction still runs on real wall-clock time. A `MutableClock` advanced far past an item's TTL in a test will not cause the backend itself to evict early — the item will simply be gone when the library's envelope check catches it on the next `getItem()` or `hasItem()` call. Tests against these three adapters should keep the clock at realistic "now" (e.g., `new MutableClock()`, not a fixed old timestamp) unless they account for this separately. The `File`, `Database`, and `Session` adapters have no separate backend-native TTL, so the injected clock is authoritative for all expiration in those three.

**Backward-compatibility note:** Any third-party subclass of an adapter that overrides its constructor without calling `parent::__construct()` will now have an uninitialized `$clock` property and throw an `Error` if accessed; while this is rare in practice (such a subclass already loses `$ttl` initialization), it is documented here for completeness.

[Top](#pop-cache)

### PSR-16 (SimpleCache) compatibility

`Pop\Cache\Cache` implements [`Psr\SimpleCache\CacheInterface`](https://www.php-fig.org/psr/psr-16/) directly —
any existing `Cache` instance is already a valid PSR-16 cache, usable anywhere a library or framework accepts one:

```php
use Pop\Cache\Cache;
use Pop\Cache\Adapter\Redis;
use Psr\SimpleCache\CacheInterface;

function useAnyPsr16Cache(CacheInterface $cache): void
{
    $cache->set('foo', 'bar', 300);
    $cache->get('foo'); // 'bar'
}

useAnyPsr16Cache(new Cache(new Redis(300)));
```

The PSR-16 methods (`get`, `set`, `delete`, `has`, `clear`, `getMultiple`, `setMultiple`, `deleteMultiple`) live
alongside this library's own pre-existing methods (`getItem`, `saveItem`, `deleteItem`, `hasItem`, `clear`,
`saveItems`, `deleteItems`) — use whichever fits the calling code. Both `get()` and `getItem()` read the same
underlying value; they just differ in their default-miss value (`null` for `get()`, matching the PSR-16 spec, vs
`false` for `getItem()`, this library's original convention). Note that `saveItems()`/`deleteItems()` (the older
array-based batch methods) do not call key validation — they were intentionally left untouched by the PSR-16 work
and behave exactly as they did before.

**TTL semantics differ between `set()` and `saveItem()`:** `saveItem($id, $value, 0)` caches the item *forever*
(this library's own convention, unchanged). `set($key, $value, 0)` — or any zero/negative TTL, including a
`\DateInterval` that resolves to zero or negative seconds — instead **deletes** the item (or never caches it),
per the PSR-16 spec's mandate. Passing `null` to either method uses the adapter's configured global TTL in both
cases.

**Upgrade Notes:**
- `Cache::clear()` now returns `bool` (always `true`) instead of `void`. Any code that hard-relies on `clear()`
  returning nothing (e.g. via strict return-type checking in a subclass override) needs updating; code that
  simply calls `$cache->clear();` without using the return value is unaffected.
- `getItem`, `saveItem`, `deleteItem`, and `hasItem` now validate their key/id argument and throw
  `Pop\Cache\InvalidArgumentException` if it contains any of `{`, `}`, `(`, `)`, `/`, `\`, `@`, `:`, or is an
  empty string — the PSR-16-mandated reserved-character set. A key using one of these characters worked
  previously (every adapter hashes the id with `sha1()` before storage, so the raw characters never reached the
  backend) but will now throw immediately instead.
- `Cache` now declares `get`, `set`, `delete`, `has`, `getMultiple`, `setMultiple`, and `deleteMultiple` as public
  methods (implementing `Psr\SimpleCache\CacheInterface`). A subclass of `Cache` that already defined a method
  with any of these names using a different, incompatible signature will now fail to load with a fatal
  "declaration must be compatible" error and needs updating to match `Psr\SimpleCache\CacheInterface`'s
  signatures.

[Top](#pop-cache)

### PSR-6 (CacheItemPoolInterface) compatibility

`Pop\Cache\Psr6\CacheItemPool` implements [`Psr\Cache\CacheItemPoolInterface`](https://www.php-fig.org/psr/psr-6/),
wrapping any of this library's adapters. Unlike PSR-16, this **cannot** live directly on `Cache` — PSR-6 declares
`getItem`/`hasItem`/`deleteItem`/`deleteItems`/`clear` with signatures incompatible with `Cache`'s own
pre-existing methods of the same names, so it's a separate class instead:

```php
use Pop\Cache\Adapter\Redis;
use Pop\Cache\Psr6\CacheItemPool;

$pool = new CacheItemPool(new Redis(300));

$item = $pool->getItem('foo');
if (!$item->isHit()) {
    $item->set('bar');
    $item->expiresAfter(300); // seconds, or pass a \DateInterval, or null to use the adapter's default TTL
    $pool->save($item);
}

$item->get(); // 'bar'
```

`CacheItemPool` and `Cache` are two independent, non-overlapping ways to reach the same adapters — construct
both around the same `Adapter\AdapterInterface` instance if an application needs the pre-existing API, PSR-16,
and PSR-6 simultaneously:

```php
use Pop\Cache\Cache;
use Pop\Cache\Adapter\Redis;
use Pop\Cache\Psr6\CacheItemPool;

$adapter = new Redis(300);
$cache   = new Cache($adapter);      // pre-existing API + PSR-16
$pool    = new CacheItemPool($adapter); // PSR-6
```

**Deferred saves:** `saveDeferred($item)` queues an item without persisting it — but the queued item IS visible
to a subsequent `getItem()`/`getItems()`/`hasItem()` call on the same pool, per PSR-6 §1.4 ("Requests for a cache
item that has been deferred MUST return the deferred but not-yet-persisted item"). `commit()` persists every
queued item and returns `true` only if all of them saved successfully. A pool also persists any still-deferred
items automatically when it's destroyed (e.g. goes out of scope without an explicit `commit()`), so deferred
writes are never silently lost.

**Expiration semantics:** an item whose resolved expiration is zero or negative seconds (via `expiresAt()` with a
past timestamp, or `expiresAfter()` with `0`/a negative `int`/a `\DateInterval` resolving to `<= 0`) is
**deleted** on `save()`/`commit()`, not cached — the opposite of `Cache::saveItem()`'s own "`0` = never expires"
convention, chosen deliberately so an already-expired item can never become accidentally permanent.

[Top](#pop-cache)

### Get-or-compute-and-cache with `remember()`

`remember()` returns a cached value if present, or invokes a callback to compute it, caches the result, and
returns it:

```php
$value = $cache->remember('expensive-report', function () {
    return generateExpensiveReport(); // only runs on a cache miss
}, 300); // TTL of 300 seconds, optional — omit to use the adapter's global TTL
```

A legitimately falsy return value (`false`, `null`, `0`, `''`) is cached correctly and won't cause the callback
to run again on the next call. If the callback throws, the exception propagates unchanged and nothing is
written to cache.

If your callback returns an object, see [A note on caching objects](#a-note-on-caching-objects) — on the
`File`, `Database`, `Redis`, and `Session` adapters it comes back as `__PHP_Incomplete_Class` on a subsequent
cache hit, not the original object.

**Steady-state stampede protection via `$beta`:** `remember(string $id, callable $callback, ?int $ttl = null,
float $beta = 0.0): mixed` — passing `$beta > 0.0` enables probabilistic early recomputation (the XFetch
algorithm, also used by Symfony Cache and Laravel): as a cached item approaches its TTL, reads have an
increasing chance of triggering recomputation *before* hard expiry, so one caller quietly refreshes the value
while every concurrent reader keeps getting served the still-valid cached copy. `$beta = 1.0` is a reasonable
starting point; higher values recompute earlier/more aggressively.

```php
$value = $cache->remember('expensive-report', function () {
    return generateExpensiveReport();
}, 300, 1.0); // TTL 300s, beta 1.0 enables early recomputation
```

**This does not protect a key's true first-ever call.** A genuine cold miss (nothing cached yet, no stampede
bookkeeping either) still lets every concurrent caller recompute simultaneously — closing that gap requires
distributed locking, which `remember()` deliberately does not implement. And the protection it does provide is
probabilistic, not a guarantee: under very high concurrency, more than one caller can still independently
trigger an early recompute around the same moment. `$beta = 0.0` (the default, and every pre-existing call
site's implicit value) disables this entirely — `remember()` behaves exactly as it always has, with no extra
cache reads or writes.

[Top](#pop-cache)

### Atomic counters with `incrementItem()`/`decrementItem()`

`incrementItem()`/`decrementItem()` provide an atomic counter primitive for use cases like rate limiting, view
counters, and concurrency-safe tallies:

```php
$views = $cache->incrementItem('page-views-123');           // creates at 0, then +1 -> 1
$views = $cache->incrementItem('page-views-123', 5);        // +5 -> 6
$attempts = $cache->decrementItem('login-attempts-user42', 1, 5); // creates at 5, then -1 -> 4

// Peek at a counter's current value without mutating it: amount 0.
$current = $cache->incrementItem('page-views-123', 0);
```

`incrementItem(string $id, int $amount = 1, int $initial = 0, ?int $ttl = null): int` and `decrementItem(...)`
(same signature) both return the counter's value after the operation. A key that doesn't exist yet is created at
`$initial` before the operation is applied — no special handling is needed for the first call.

**Atomicity varies by adapter.** `Apc`, `Memcached`, and `Redis` provide genuine backend-native atomicity by
storing the counter as a raw scalar value, completely bypassing the envelope `getItem()`/`saveItem()` normally
use — **a counter set with `incrementItem()`/`decrementItem()` is not readable via `getItem()`/`getItemTtl()` on
these three adapters**, and vice versa: calling `saveItem()` on a key you're also using as a counter (or
`incrementItem()` on a key you're also using with `saveItem()`) will not behave as expected. Treat a given cache
key as *either* a counter *or* a regular cached value on `Apc`/`Memcached`/`Redis`, never both. `File`, `Database`,
`Session`, `Memory`, and `NullAdapter` have no native atomic-increment primitive, so they implement
`incrementItem()`/`decrementItem()` as an explicitly **non-atomic** read-modify-write through the normal
`getItem()`/`saveItem()` envelope — on these five, a counter is an ordinary cached integer, fully interoperable
with the rest of the API.

**TTL behavior also diverges by adapter, and matters for rate limiting.** On `Apc`/`Memcached`/`Redis`, a
counter's TTL is set once when it's first created and is never refreshed by later calls — the counter expires on
schedule regardless of how often it's touched afterward. On `File`/`Database`/`Session`/`Memory`, by contrast,
every `incrementItem()`/`decrementItem()` call — **including a `$amount = 0` peek** — rewrites the item through
`saveItem()` and restarts its TTL countdown. A rate-limiter pattern like `decrementItem('login-attempts-user42',
1, 5, 60)` will correctly expire after 60 seconds of inactivity on the three atomic adapters, but on the five
envelope-based adapters, sustained traffic (or even repeated peeks) keeps restarting the 60-second window,
so the limit never actually resets. Plan accordingly, or use `Apc`/`Memcached`/`Redis` specifically for
rate-limiting use cases.

By default, `decrementItem()` allows the result to go negative (matching Redis's `DECRBY`/APCu's `apcu_dec()`) —
**except on `Memcached`**, whose native `decrement()` clamps at `0` at the protocol level and cannot return a
negative value.

**Note:** enabling `incrementItem()`/`decrementItem()` support required switching the `Memcached` adapter to the
binary protocol (`Memcached::OPT_BINARY_PROTOCOL`), which is now enabled unconditionally for every `Memcached`
adapter instance, not just ones using counters — the default ASCII protocol silently drops the custom initial
value counters need. This is fully compatible with this adapter's existing `get`/`set`/`delete` usage.

A value already in cache under the same key that isn't an integer causes `incrementItem()`/`decrementItem()` to
throw `Pop\Cache\Adapter\Exception` — **except on `Redis` and `Memcached`**, which store counters as wire-protocol
strings and cannot distinguish a numeric string (e.g. `'5'`) from a real integer; a pre-existing numeric string
is silently incremented rather than rejected on those two adapters.

[Top](#pop-cache)

### Bulk invalidation with tags

`saveTaggedItem()` associates one or more tags with a saved item; `invalidateTag()`/`invalidateTags()` delete
every item under a tag in one call, without needing to know their individual ids in advance:

```php
$cache->saveTaggedItem('product-1', $productData, ['products', 'category-electronics']);
$cache->saveTaggedItem('product-2', $otherProductData, ['products', 'category-books']);

// Something changed about electronics products in general:
$cache->invalidateTag('category-electronics'); // deletes product-1, leaves product-2 alone

// Invalidate several tags in one call:
$cache->invalidateTags(['products', 'category-books']);
```

Note: like every other key `Cache` accepts, both `$id` and each tag name are validated by the same PSR-16
reserved-character rule (`{`, `}`, `(`, `)`, `/`, `\`, `@`, `:`, or empty) — a tag or id containing `:` (a
common convention in other caching libraries for namespacing, e.g. `"category:electronics"`) will throw
`InvalidArgumentException` here, same as it always has for `saveItem()`/`getItem()`. Use `-` or `.` instead.

Re-saving an id with a different tag set correctly removes it from tags it no longer belongs to. Invalidating one
tag on a multi-tagged item also removes that item from its other tags' bookkeeping, so no tag accumulates
permanently-stale references to items already deleted via a different tag.

**No atomicity guarantees, no introspection API.** Every operation here is a plain, non-atomic read-modify-write
— a race between two concurrent `saveTaggedItem()` calls could rarely lose a tag membership, same trade-off as
`incrementItem()`/`decrementItem()` on the non-atomic adapters. There is no way to list which items currently
carry a tag; tags exist purely for bulk invalidation.

**A tag's membership index has no size cap and doesn't self-heal.** Every id ever saved under a tag stays in
that tag's index until the tag is invalidated or the id is explicitly re-tagged away from it — even after the
id's own item has separately expired. On `Apc`/`Memcached`/`Redis` specifically, this bookkeeping index is an
ordinary cache item and is just as subject to backend eviction (LRU under memory pressure, or Memcached's
per-item size limit for a very large tag) as any other cached value — if a tag's index is evicted or fails to
store before `invalidateTag()` runs, that call silently finds nothing and invalidates none of that tag's
members, leaving stale data behind rather than raising an error.

**Don't mix plain `saveItem()`/`deleteItem()` — or any other way of writing to the same key — with
`saveTaggedItem()` on that key.** This includes the PSR-16 `set()`/`delete()`/`setMultiple()` methods,
`remember()`, and the `$cache['key'] = ...`/`$cache->key = ...` `ArrayAccess`/magic-property sugar — all of
them ultimately call the adapter's plain `saveItem()`/`deleteItem()`, none of them know about tag bookkeeping.
Writing to a previously-tagged key through any of these doesn't update its tag associations — a later
`invalidateTag()` will still delete that key's (now untagged, from the caller's perspective) current value.
Deleting a tagged key through any of these leaves stale bookkeeping behind until the tag is next invalidated.
Once you start tagging a key, keep using `saveTaggedItem()` for every write to it (pass `[]` for `$tags` to stop
tagging it) and invalidate it via its tag rather than any other delete path.

[Top](#pop-cache)

### Check if the cache has an item

```php
if ($cache->hasItem('foo')) {
    // ...
}
```

### Save items

```php
$cache->saveItems([
    'foo' => 'bar',
    'baz' => 'qux',
]);
```

A convenience loop over `saveItem()` for multiple items at once. Every item uses the adapter's global TTL —
`saveItems()` has no way to pass a per-item TTL; call `saveItem()` individually if a batch needs mixed TTLs.

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

### Destroy the cache resource

```php
$cache->destroy();
```

Unlike `clear()` (which empties the cache but leaves it usable), `destroy()` tears down the adapter's underlying
resource — e.g. `File` removes the cache directory itself, `Database` drops all rows (the table is recreated on
next use), and `Apc`/`Memcached`/`Redis` scope to the adapter's namespace the same way `clear()` does.

**`Session` is the one exception, and it's worth knowing about before you call it:** `Session::destroy()` calls
PHP's own `session_destroy()`, tearing down the *entire* PHP session — not just this cache's namespace within
it. If your application stores anything else in the session (auth state, flash messages, etc.), calling
`destroy()` on a `Session`-backed cache wipes all of it, not just the cache. Use `clear()` instead if you only
want to empty the cache's own namespace.

### Adapter introspection

```php
Cache::getAvailableAdapters(); // ['apc' => bool, 'file' => true, 'memcached' => bool, 'memory' => true, 'null' => true, 'redis' => bool, 'session' => bool, 'sqlite' => bool]
Cache::isAvailable('redis');   // bool

$cache->adapter(); // the underlying Adapter\AdapterInterface instance
$cache->getTtl();  // the adapter's configured global TTL
```

`getAvailableAdapters()`/`isAvailable()` are static and check what's actually usable in the *current* PHP
runtime (e.g. whether the `redis` extension is loaded), not whether a backing service is actually reachable —
useful for choosing an adapter dynamically without hardcoding assumptions about the deployment environment.
`Memory` and `NullAdapter` are always reported available, since neither has an external dependency to probe for.

[Top](#pop-cache)

APC
---

Using the APC adapter requires APC to be correctly set up in the environment.

```php
use Pop\Cache\Cache;
use Pop\Cache\Adapter\Apc;

$cache = new Cache(new Apc(300));
```

The constructor also accepts an optional trailing `$namespace` parameter (default `'pop_cache'`), so multiple caches can safely share one APC instance — `clear()`/`destroy()` only affect the calling cache's namespace.

```php
$cache = new Cache(new Apc(300, 'my-app'));
```

**Upgrade Note:** Entries cached before this version was introduced were written with the raw `$id` as the key suffix (`namespace:v1:$id`) — or, if upgrading from a version before namespacing was added at all, under a bare, unnamespaced `$id` — neither of which matches the current namespaced-and-hashed format (`namespace:v1:sha1($id)`), so after upgrading they become unreachable and the cache effectively cold-starts on deploy (a silent 100% miss rate until repopulated) — no action is required, but it's worth planning around.

[Top](#pop-cache)

Memcached
---------

Using the Memcached adapter requires Memcached to be correctly set up in the environment.

```php
use Pop\Cache\Cache;
use Pop\Cache\Adapter\Memcached;

$cache = new Cache(new Memcached(300, 'localhost', 11211));
```

The constructor also accepts an optional trailing `$namespace` parameter (default `'pop_cache'`), so multiple caches can safely share one Memcached instance — `clear()`/`destroy()` only affect the calling cache's namespace.

```php
$cache = new Cache(new Memcached(300, 'localhost', 11211, 1, 'my-app'));
```

**Upgrade Note:** Entries cached before this version was introduced were written with the raw `$id` as the key suffix (`namespace:v1:$id`) — or, if upgrading from a version before namespacing was added at all, under a bare, unnamespaced `$id` — neither of which matches the current namespaced-and-hashed format (`namespace:v1:sha1($id)`), so after upgrading they become unreachable and the cache effectively cold-starts on deploy (a silent 100% miss rate until repopulated) — no action is required, but it's worth planning around.

[Top](#pop-cache)

Redis
-----

Using the Redis adapter requires Redis to be correctly set up in the environment.

```php
use Pop\Cache\Cache;
use Pop\Cache\Adapter\Redis;

$cache = new Cache(new Redis(300, 'localhost', 6379));
```

The constructor also accepts an optional trailing `$namespace` parameter (default `'pop_cache'`), so multiple caches can safely share one Redis instance — `clear()`/`destroy()` only affect the calling cache's namespace.

```php
$cache = new Cache(new Redis(300, 'localhost', 6379, 'my-app'));
```

**Upgrade Note:** Entries cached before this version was introduced were written with the raw `$id` as the key suffix (`namespace:v1:$id`) — or, if upgrading from a version before namespacing was added at all, under a bare, unnamespaced `$id` — neither of which matches the current namespaced-and-hashed format (`namespace:v1:sha1($id)`), so after upgrading they become unreachable and the cache effectively cold-starts on deploy (a silent 100% miss rate until repopulated) — no action is required, but it's worth planning around.

[Top](#pop-cache)

File
----

Using the file adapter will simply store the cache data on the local disk.

```php
use Pop\Cache\Cache;
use Pop\Cache\Adapter\File;

$cache = new Cache(new File('/path/to/my/cache/dir', 300));
```

**Upgrade Note:** Cache file writes are now atomic (written to a temp file, then renamed into place) — no action required, this is a pure reliability improvement. Cache files are also now sharded into subdirectories (by the first 2 characters of each item's hash) to keep large caches from putting too many files in one directory — existing files from before this version were stored flat and become unreachable after upgrading. No action is required: a subsequent `clear()` call will opportunistically remove any stray flat files it finds at the top level of the cache directory, or the old cache directory can be cleared manually.

[Top](#pop-cache)

Database
--------

Using the database adapter will require the database to be set up correctly and the use of
the `pop-db` component.

```php
use Pop\Cache\Cache;
use Pop\Cache\Adapter\Database;
use Pop\Db\Db;

$cache = new Cache(
    new Database(Db::sqliteConnect(['database' => __DIR__ . '/tmp/cache.sqlite']), 300)
);
```

**Upgrade Note:** Existing tables (default name `pop_cache`) created by earlier versions of this package lack a unique index on the `key` column, and dropping the table is **required before the upgraded code writes anything** — this isn't just a lingering-bug cleanup. The upgraded `saveItem()` issues an upsert targeting a unique index that doesn't exist on the old table, which produces two different failure modes depending on driver: on SQLite and PostgreSQL, every `saveItem()` call will throw (e.g. `ON CONFLICT clause does not match any PRIMARY KEY or UNIQUE constraint`); on MySQL, `ON DUPLICATE KEY UPDATE` never finds a matching key to conflict on, so instead of an error, every write silently accumulates a duplicate row. Drop the table before upgrading (it will be automatically recreated with the corrected schema on next use, since cache data is disposable), substituting your own table name below if you passed a custom `$table` value to the `Database` adapter's constructor:

```sql
DROP TABLE pop_cache;
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

The constructor also accepts an optional trailing `$namespace` parameter (default `'pop_cache'`), so multiple caches can safely share one session — `clear()`/`destroy()` only affect the calling cache's namespace.

```php
$cache = new Cache(new Session(300, 'my-app'));
```

**Upgrade Note:** The key format has changed twice. Entries cached before namespacing was added at all were stored under a bare, unnamespaced `sha1($id)`. A later version namespaced and versioned the key (`namespace:v1:sha1($id)`) to support generational `clear()`. The current format drops the version segment entirely (`namespace:sha1($id)`), since this adapter's `clear()` deletes matching keys directly by prefix rather than via generational versioning — there's no backend-scan limitation here to work around, unlike Apc/Memcached/Redis. Entries written under either older format don't match the current key a lookup computes, so after upgrading through either transition they become unreachable and the cache effectively cold-starts on deploy (a silent 100% miss rate until repopulated) — no action is required, but it's worth planning around; the old-format entries also linger in `$_SESSION['_POP_CACHE_']` until something else clears them, since they don't match the prefix the current `clear()` sweeps.

[Top](#pop-cache)

Memory
------

Using the memory adapter stores cache data in a plain PHP array for the lifetime of the object — useful for tests or single-request caching without needing an external service.

```php
use Pop\Cache\Cache;
use Pop\Cache\Adapter\Memory;

$cache = new Cache(new Memory(300));
```

Unlike every other adapter, cached objects are never serialized — `getItem()` returns the exact original object instance, not a `__PHP_Incomplete_Class` stand-in. Data is never shared between separate `Memory` instances and does not persist beyond the object's own lifetime. Because the cached object is the live original (not a snapshot), mutating it after `saveItem()` is visible through subsequent `getItem()` calls — unlike scalars/arrays, which are copied by value.

[Top](#pop-cache)

Null
----

Using the null adapter disables caching entirely without changing any calling code — `saveItem()` is a no-op, and every read is unconditionally a cache miss.

```php
use Pop\Cache\Cache;
use Pop\Cache\Adapter\NullAdapter;

$cache = new Cache(new NullAdapter());
```

[Top](#pop-cache)
