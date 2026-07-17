<?php

declare(strict_types=1);

/**
 * ParseException
 *
 * Thrown when the env parser encounters a syntax or structural error.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Env\Exception;

final class ParseException extends EnvException
{
    /**
     * Create the exception, prefixing the source line when known.
     *
     * @param string $message A description of the parse error.
     * @param int $line The line number where the error occurred, or 0 if unknown.
     */
    public function __construct(string $message, int $line = 0)
    {
        parent::__construct(
            $line > 0 ? "Line {$line}: {$message}" : $message,
        );

        $this->line = $line;
    }
}
