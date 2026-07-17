<?php

declare(strict_types=1);

/**
 * The field must not be present in the payload when another field equals one
 * of the given values.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class MissingIf extends Rule
{
    /**
     * The field must be absent when another field's value is one of the given values.
     *
     * @param string $otherField The other field whose value is checked
     * @param list<mixed> $values Values of $otherField that require this field to be absent
     */
    public function __construct(
        private readonly string $otherField,
        private readonly array $values,
    ) {}

    public function passes(mixed $value, ValidationContext $context): bool
    {
        $otherValue = $context->value($this->otherField);

        if (!in_array($otherValue, $this->values, true)) {
            return true;
        }

        return !$context->has($context->field());
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
