<?php

declare(strict_types=1);

/**
 * The value must match the given regex pattern (PCRE).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Regex extends Rule
{
    /**
     * __construct.
     *
     * @param string $pattern
     */
    public function __construct(
        private readonly string $pattern,
    ) {}

    public function passes(mixed $value, ValidationContext $context): bool
    {
        return is_string($value) && preg_match($this->pattern, $value) === 1;
    }

    public function params(ValidationContext $context): array
    {
        return [...parent::params($context), 'pattern' => $this->pattern];
    }
}
