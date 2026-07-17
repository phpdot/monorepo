<?php

declare(strict_types=1);

/**
 * The number of days between two date fields must not exceed `$max`.
 *
 * Example:
 * `new DaysBetween('start_date', 'end_date', max: 30)`
 * — fails if `end_date` is more than 30 days after `start_date`.
 *
 * The rule passes the check independent of which field it's attached to;
 * it operates on `start` and `end` field names from the payload.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class DaysBetween extends Rule
{
    private const SECONDS_PER_DAY = 86400;

    /**
     * __construct.
     *
     * @param string $startField
     * @param string $endField
     * @param int $max
     */
    public function __construct(
        private readonly string $startField,
        private readonly string $endField,
        private readonly int $max,
    ) {}

    public function passes(mixed $value, ValidationContext $context): bool
    {
        $startTs = Date::parse($context->value($this->startField));
        $endTs = Date::parse($context->value($this->endField));

        if ($startTs === null || $endTs === null) {
            return false;
        }

        $diffDays = (int) floor(abs($endTs - $startTs) / self::SECONDS_PER_DAY);

        return $diffDays <= $this->max;
    }

    public function params(ValidationContext $context): array
    {
        return [
            ...parent::params($context),
            'start' => $this->startField,
            'end' => $this->endField,
            'max' => $this->max,
        ];
    }
}
