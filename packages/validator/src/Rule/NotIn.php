<?php

declare(strict_types=1);

/**
 * The value must NOT be in the given list (strict comparison).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class NotIn extends Rule
{
    /**
     * @var list<mixed>
     */
    private readonly array $values;

    /**
     * __construct.
     *
     * @param mixed $values
     */
    public function __construct(mixed ...$values)
    {
        $this->values = array_values($values);
    }

    public function passes(mixed $value, ValidationContext $context): bool
    {
        return !in_array($value, $this->values, true);
    }

    public function params(ValidationContext $context): array
    {
        return [...parent::params($context), 'values' => $this->values];
    }
}
