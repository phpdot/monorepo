<?php

declare(strict_types=1);

/**
 * Base exception for the cache package.
 *
 * Implements PSR-16's CacheException so every failure this package raises is
 * catchable both as the package hierarchy and as the interface the spec
 * defines. Argument errors use the sibling InvalidArgumentException, whose
 * PSR-16 interface extends the same CacheException contract.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Cache\Exception;

use RuntimeException;

class CacheException extends RuntimeException implements \Psr\SimpleCache\CacheException {}
