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
namespace Pop\Cache;

/**
 * Shared cache key validation, used by both the PSR-16 (Cache) and PSR-6 (Psr6\CacheItemPool) surfaces
 *
 * @category   Pop
 * @package    Pop\Cache
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.0.0
 */
trait ValidatesKey
{

    /**
     * Validate a cache key (throws on reserved characters or an empty string)
     *
     * @param  string $key
     * @throws InvalidArgumentException
     * @return void
     */
    protected function validateKey(string $key): void
    {
        if (($key === '') || (preg_match('#[{}()/\\\\@:]#', $key) === 1)) {
            throw new InvalidArgumentException(
                'Error: The cache key contains one or more reserved characters, or is empty.'
            );
        }
    }

}
