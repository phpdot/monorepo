<?php

declare(strict_types=1);

/**
 * Base exception for the attribute package.
 *
 * Raised when the scanner is used before a scan has run, or when the file
 * cache cannot be written. Extends RuntimeException, keeping pre-hierarchy
 * catch sites working.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Attribute\Exception;

use RuntimeException;

class AttributeException extends RuntimeException {}
