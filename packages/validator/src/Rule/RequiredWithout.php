<?php

declare(strict_types=1);

/**
 * The field is required when ANY of the given fields are missing or empty.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class RequiredWithout extends Rule
{
    /**
     * @var list<string>
     */
    private readonly array $fields;

    /**
     * __construct.
     *
     * @param string $fields
     */
    public function __construct(string ...$fields)
    {
        $this->fields = array_values($fields);
    }

    public function passes(mixed $value, ValidationContext $context): bool
    {
        $anyMissing = false;

        foreach ($this->fields as $other) {
            if (!$context->has($other) || self::isEmpty($context->value($other))) {
                $anyMissing = true;

                break;
            }
        }

        if (!$anyMissing) {
            return true;
        }

        if (!$context->has($context->field())) {
            return false;
        }

        return !self::isEmpty($value);
    }

    public function params(ValidationContext $context): array
    {
        return [...parent::params($context), 'others' => $this->fields];
    }
}
