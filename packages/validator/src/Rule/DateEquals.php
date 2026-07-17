<?php

declare(strict_types=1);

/**
 * The value's date must equal the reference (literal date or field name).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class DateEquals extends Rule
{
    /**
     * __construct.
     *
     * @param mixed $reference
     */
    public function __construct(
        private readonly mixed $reference,
    ) {}

    public function passes(mixed $value, ValidationContext $context): bool
    {
        $valueTs = Date::parse($value);
        $referenceTs = Date::parse($context->dereference($this->reference));

        return $valueTs !== null && $referenceTs !== null && $valueTs === $referenceTs;
    }

    public function params(ValidationContext $context): array
    {
        return [...parent::params($context), 'other' => $this->reference];
    }
}
