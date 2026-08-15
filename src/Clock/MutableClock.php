<?php
declare(strict_types=1);
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
namespace Pop\Cache\Clock;

/**
 * Mutable clock class
 *
 * A clock whose time can be explicitly set and advanced, for deterministic testing of TTL/expiration behavior
 * without real sleep() calls.
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.0.0
 */
class MutableClock implements ClockInterface
{

    /**
     * Current time as a Unix timestamp
     * @var int
     */
    protected int $time;

    /**
     * Constructor
     *
     * Instantiate the mutable clock object
     *
     * @param  ?int $time
     */
    public function __construct(?int $time = null)
    {
        $this->time = $time ?? time();
    }

    /**
     * Get the current time as a Unix timestamp
     *
     * @return int
     */
    public function now(): int
    {
        return $this->time;
    }

    /**
     * Set the current time
     *
     * @param  int $time
     * @return MutableClock
     */
    public function setTime(int $time): MutableClock
    {
        $this->time = $time;
        return $this;
    }

    /**
     * Advance the current time by a number of seconds
     *
     * @param  int $seconds
     * @return MutableClock
     */
    public function advance(int $seconds): MutableClock
    {
        $this->time += $seconds;
        return $this;
    }

}
