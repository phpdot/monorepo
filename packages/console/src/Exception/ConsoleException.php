<?php

declare(strict_types=1);

/**
 * Base exception for the console package.
 *
 * Raised for registration misuse — aliasing, renaming, or overriding a
 * command that does not exist, or colliding with one that does. Extends
 * RuntimeException, keeping pre-hierarchy catch sites working.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Console\Exception;

use RuntimeException;

class ConsoleException extends RuntimeException {}
