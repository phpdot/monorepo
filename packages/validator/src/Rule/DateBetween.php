<?php

declare(strict_types=1);

/**
 * The value's date must fall between two references (inclusive).
 *
 * Each reference can be a literal date string, a `DateTimeInterface`, or a
 * field name from the payload.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class DateBetween extends Rule
{
    /**
     * __construct.
     *
     * @param mixed $start
     * @param mixed $end
     */
    public function __construct(
        private readonly mixed $start,
        private readonly mixed $end,
    ) {}

    public function passes(mixed $value, ValidationContext $context): bool
    {
        $valueTs = Date::parse($value);
        $startTs = Date::parse($context->dereference($this->start));
        $endTs = Date::parse($context->dereference($this->end));

        if ($valueTs === null || $startTs === null || $endTs === null) {
            return false;
        }

        return $valueTs >= $startTs && $valueTs <= $endTs;
    }

    public function params(ValidationContext $context): array
    {
        return [...parent::params($context), 'start' => $this->start, 'end' => $this->end];
    }
}
