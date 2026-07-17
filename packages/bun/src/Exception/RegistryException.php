<?php

declare(strict_types=1);

/**
 * Thrown when a request to the npm registry fails or returns an unexpected payload.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Exception;

final class RegistryException extends \RuntimeException implements BunException {}
