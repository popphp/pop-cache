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
namespace Pop\Cache\Psr6;

/**
 * PSR-6 cache item class
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.0.0
 */
class CacheItem implements \Psr\Cache\CacheItemInterface
{

    /**
     * The cache item key
     * @var string
     */
    protected string $key;

    /**
     * The cache item value
     * @var mixed
     */
    protected mixed $value = null;

    /**
     * Whether the item was a cache hit
     * @var bool
     */
    protected bool $isHit = false;

    /**
     * The resolved expiration, in seconds from now (null means "use the pool's default")
     * @var ?int
     */
    protected ?int $expirationSeconds = null;

    /**
     * Constructor
     *
     * Instantiate the cache item object
     *
     * @param  string $key
     * @param  mixed  $value
     * @param  bool   $isHit
     */
    public function __construct(string $key, mixed $value = null, bool $isHit = false)
    {
        $this->key   = $key;
        $this->value = $value;
        $this->isHit = $isHit;
    }

    /**
     * Get the cache item key
     *
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Get the cache item value
     *
     * @return mixed
     */
    public function get(): mixed
    {
        return $this->value;
    }

    /**
     * Determine if the cache item lookup was a hit
     *
     * @return bool
     */
    public function isHit(): bool
    {
        return $this->isHit;
    }

    /**
     * Set the cache item value
     *
     * @param  mixed $value
     * @return static
     */
    public function set(mixed $value): static
    {
        $this->value = $value;
        return $this;
    }

    /**
     * Set the expiration time for this cache item
     *
     * @param  ?\DateTimeInterface $expiration
     * @return static
     */
    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        $this->expirationSeconds = ($expiration !== null) ? max(0, $expiration->getTimestamp() - time()) : null;
        return $this;
    }

    /**
     * Set the expiration time for this cache item, relative to now
     *
     * @param  int|\DateInterval|null $time
     * @return static
     */
    public function expiresAfter(int|\DateInterval|null $time): static
    {
        if ($time instanceof \DateInterval) {
            $now = new \DateTimeImmutable();
            $this->expirationSeconds = $now->add($time)->getTimestamp() - $now->getTimestamp();
        } else {
            $this->expirationSeconds = $time;
        }

        return $this;
    }

    /**
     * Get the resolved expiration, in seconds from now (null means "use the pool's default")
     *
     * @return ?int
     */
    public function getExpirationSeconds(): ?int
    {
        return $this->expirationSeconds;
    }

}
