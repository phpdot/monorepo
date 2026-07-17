<?php

declare(strict_types=1);

/**
 * Entry
 *
 * Immutable value object representing a single parsed key-value pair from an env file.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Env\Parser;

final readonly class Entry
{
    /**
     * Create one parsed key/value entry with its source line.
     *
     * @param string $key The environment variable name.
     * @param string $value The raw or resolved value.
     * @param int $line The line number where this entry was defined.
     * @param bool $interpolate Whether variable interpolation should be applied.
     */
    public function __construct(
        public string $key,
        public string $value,
        public int $line,
        public bool $interpolate = true,
    ) {}
}
