<?php

declare(strict_types=1);

/**
 * The value must start with one of the given prefixes.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class StartsWith extends Rule
{
    /**
     * @var list<string>
     */
    private readonly array $prefixes;

    /**
     * __construct.
     *
     * @param string $prefixes
     */
    public function __construct(string ...$prefixes)
    {
        $this->prefixes = array_values($prefixes);
    }

    public function passes(mixed $value, ValidationContext $context): bool
    {
        if (!is_string($value)) {
            return false;
        }

        foreach ($this->prefixes as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function params(ValidationContext $context): array
    {
        return [...parent::params($context), 'prefixes' => $this->prefixes];
    }
}
