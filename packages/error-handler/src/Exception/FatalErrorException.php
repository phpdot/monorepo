<?php

declare(strict_types=1);

/**
 * Wraps PHP fatal errors (from error_get_last) as an exception.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\ErrorHandler\Exception;

final class FatalErrorException extends \ErrorException
{
    /**
     * Create from PHP's error_get_last() array.
     *
     * @param array{type: int, message: string, file: string, line: int} $error
     *
     * @return self
     */
    public static function fromLastError(array $error): self
    {
        return new self(
            message: $error['message'],
            code: 0,
            severity: $error['type'],
            filename: $error['file'],
            line: $error['line'],
        );
    }
}
