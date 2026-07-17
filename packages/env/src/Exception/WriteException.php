<?php

declare(strict_types=1);

/**
 * WriteException
 *
 * Thrown when writing to an environment file fails.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Env\Exception;

final class WriteException extends EnvException
{
    /**
     * Creates an exception indicating that the operation requires CLI context.
     *
     * @return self
     */
    public static function notCli(): self
    {
        return new self('EnvEditor can only be used from CLI');
    }

    /**
     * Creates an exception for a failed file write operation.
     *
     * @param string $path The path that could not be written to.
     *
     * @return WriteException
     */
    public static function writeFailed(string $path): self
    {
        return new self("Failed to write: {$path}");
    }
}
