<?php

declare(strict_types=1);

/**
 * Thrown when the host OS/architecture combination has no published Bun binary.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Exception;

final class UnsupportedPlatformException extends \RuntimeException implements BunException {}
