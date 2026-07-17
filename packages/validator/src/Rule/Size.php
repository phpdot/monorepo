<?php

declare(strict_types=1);

/**
 * Exact size: numeric value, string length (mb), or array count.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Size extends Rule
{
    /**
     * __construct.
     *
     * @param int|float $size
     */
    public function __construct(
        private readonly int|float $size,
    ) {}

    public function passes(mixed $value, ValidationContext $context): bool
    {
        return self::measure($value) === (float) $this->size;
    }

    public function params(ValidationContext $context): array
    {
        return [...parent::params($context), 'size' => $this->size];
    }

    /**
     * Resolve a numeric "size" for any value.
     *
     * - Numeric (int/float/numeric-string) → its numeric value.
     * - String → multibyte length.
     * - Array → element count.
     * - Anything else → 0.
     *
     * @param mixed $value
     *
     * @return float
     */
    public static function measure(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            return (float) mb_strlen($value);
        }

        if (is_array($value)) {
            return (float) count($value);
        }

        return 0.0;
    }
}
