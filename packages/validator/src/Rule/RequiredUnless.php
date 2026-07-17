<?php

declare(strict_types=1);

/**
 * The field is required UNLESS another field equals one of the given values.
 *
 * Example: `new RequiredUnless('account_type', ['guest'])` — required for
 * any account type except `guest`.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class RequiredUnless extends Rule
{
    /**
     * Require this field unless another field's value is one of the given values.
     *
     * @param string $otherField The other field whose value is checked
     * @param list<mixed> $values Values of $otherField that make this field optional
     */
    public function __construct(
        private readonly string $otherField,
        private readonly array $values,
    ) {}

    public function passes(mixed $value, ValidationContext $context): bool
    {
        $otherValue = $context->value($this->otherField);

        if (in_array($otherValue, $this->values, true)) {
            return true;
        }

        if (!$context->has($context->field())) {
            return false;
        }

        return !self::isEmpty($value);
    }

    public function params(ValidationContext $context): array
    {
        return [
            ...parent::params($context),
            'other' => $this->otherField,
            'values' => $this->values,
        ];
    }
}
