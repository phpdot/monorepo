<?php

declare(strict_types=1);

/**
 * PSR-16 required exception for invalid cache arguments.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Cache\Exception;

final class InvalidArgumentException extends \InvalidArgumentException implements \Psr\SimpleCache\InvalidArgumentException {}
