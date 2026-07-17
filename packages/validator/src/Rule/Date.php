<?php

declare(strict_types=1);

/**
 * The value must be parseable as a date (string accepted by `strtotime`,
 * or a `DateTimeInterface` instance).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use DateTimeInterface;
use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Date extends Rule
{
    public function passes(mixed $value, ValidationContext $context): bool
    {
        return self::parse($value) !== null;
    }

    /**
     * Parse a value into a Unix timestamp, or null when it is not a valid date.
     *
     * @param mixed $value The value to interpret as a date
     *
     * @return ?int Unix timestamp, or null if unparseable
     */
    public static function parse(mixed $value): ?int
    {
        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : $timestamp;
    }
}
