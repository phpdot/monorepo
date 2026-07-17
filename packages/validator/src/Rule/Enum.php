<?php

declare(strict_types=1);

/**
 * The value must be a backing value of the given backed enum.
 *
 * Validation reads cases directly from the enum class — single source of truth.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use BackedEnum;
use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

/**
 * @template T of BackedEnum
 */
final class Enum extends Rule
{
    /**
     * Validate the value against the cases of the given backed enum.
     *
     * @param class-string<T> $enum The backed-enum class whose cases are the allowed values
     */
    public function __construct(
        private readonly string $enum,
    ) {}

    public function passes(mixed $value, ValidationContext $context): bool
    {
        if (!is_string($value) && !is_int($value)) {
            return false;
        }

        if (!is_subclass_of($this->enum, BackedEnum::class)) {
            return false;
        }

        return $this->enum::tryFrom($value) !== null;
    }

    public function params(ValidationContext $context): array
    {
        return [...parent::params($context), 'enum' => $this->enum];
    }
}
