<?php

declare(strict_types=1);

/**
 * SchemaException
 *
 * Thrown when a schema definition is invalid or references an unknown key.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Env\Exception;

final class SchemaException extends EnvException
{
    /**
     * Creates an exception for an unrecognized env key.
     *
     * @param string $key The unknown key name.
     *
     * @return self
     */
    public static function unknownKey(string $key): self
    {
        return new self("Unknown env key: {$key}");
    }

    /**
     * Creates an exception for an invalid schema definition.
     *
     * @param string $key The key with the invalid definition.
     * @param string $reason A description of why the definition is invalid.
     *
     * @return SchemaException
     */
    public static function invalidDefinition(string $key, string $reason): self
    {
        return new self("Invalid schema definition for '{$key}': {$reason}");
    }
}
