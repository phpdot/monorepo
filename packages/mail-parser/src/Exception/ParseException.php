<?php

declare(strict_types=1);

/**
 * Base exception class for all parsing errors in the mail parser library.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Exception;

use RuntimeException;
use Throwable;

abstract class ParseException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int|null $byteOffset = null,
        Throwable|null $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
