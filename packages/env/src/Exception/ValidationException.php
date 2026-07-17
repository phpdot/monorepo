<?php

declare(strict_types=1);

/**
 * ValidationException
 *
 * Thrown when env values fail validation against their schema definitions.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Env\Exception;

final class ValidationException extends EnvException
{
    /**
     * Creates an exception for missing required env keys.
     *
     * @param array<int, string> $keys The list of missing key names.
     *
     * @return self
     */
    public static function missingRequired(array $keys): self
    {
        return new self('Required env keys missing: ' . implode(', ', $keys));
    }

    /**
     * Creates an exception for a value that does not match its expected type.
     *
     * @param string $key The key with the type mismatch.
     * @param string $expected The expected type description.
     * @param string $actual The actual value that was provided.
     *
     * @return ValidationException
     */
    public static function typeMismatch(string $key, string $expected, string $actual): self
    {
        return new self("Invalid value for '{$key}': expected {$expected}, got '{$actual}'");
    }

    /**
     * Creates an exception for a value that fails a constraint check.
     *
     * @param string $key The key that failed the constraint.
     * @param string $constraint A description of the failed constraint.
     *
     * @return ValidationException
     */
    public static function constraintFailed(string $key, string $constraint): self
    {
        return new self("Constraint failed for '{$key}': {$constraint}");
    }
}
