<?php

declare(strict_types=1);

/**
 * The value must match the given date format exactly (no truncation).
 *
 * Example: `new DateFormat('Y-m-d')` accepts `'2024-04-15'` but rejects
 * `'2024-04-15 10:00'` and `'2024-4-15'`.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use DateTimeImmutable;
use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class DateFormat extends Rule
{
    /**
     * __construct.
     *
     * @param string $format
     */
    public function __construct(
        private readonly string $format,
    ) {}

    public function passes(mixed $value, ValidationContext $context): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $parsed = DateTimeImmutable::createFromFormat($this->format, $value);

        return $parsed !== false && $parsed->format($this->format) === $value;
    }

    public function params(ValidationContext $context): array
    {
        return [...parent::params($context), 'format' => $this->format];
    }
}
