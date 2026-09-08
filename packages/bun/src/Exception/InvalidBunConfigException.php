<?php

declare(strict_types=1);

/**
 * Thrown when config/bun.php carries a value the pipeline cannot use — a directory key
 * that is empty (not yet installed) or relative (would resolve against the working
 * directory and re-download the runtime per invocation).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Exception;

use RuntimeException;

final class InvalidBunConfigException extends RuntimeException implements BunException
{
    /**
     * A directory key left at its scaffolded empty value.
     *
     * @param string $key The config key that is empty
     *
     * @return self
     */
    public static function notSet(string $key): self
    {
        return new self(
            "bun config '{$key}' is not set — the install hook fills it at composer time, "
            . "or set it to an absolute path (dirname(__DIR__) . '/…' keeps the file portable)",
        );
    }

    /**
     * A directory key carrying a relative path.
     *
     * @param string $key The config key
     * @param string $value The relative value
     *
     * @return self
     */
    public static function relativePath(string $key, string $value): self
    {
        return new self(
            "bun config '{$key}' must be an absolute path — '{$value}' would resolve against "
            . 'the working directory and re-download per invocation',
        );
    }
}
