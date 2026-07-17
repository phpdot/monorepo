<?php

declare(strict_types=1);

/**
 * The value must end with one of the given suffixes.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class EndsWith extends Rule
{
    /**
     * @var list<string>
     */
    private readonly array $suffixes;

    /**
     * __construct.
     *
     * @param string $suffixes
     */
    public function __construct(string ...$suffixes)
    {
        $this->suffixes = array_values($suffixes);
    }

    public function passes(mixed $value, ValidationContext $context): bool
    {
        if (!is_string($value)) {
            return false;
        }

        foreach ($this->suffixes as $suffix) {
            if (str_ends_with($value, $suffix)) {
                return true;
            }
        }

        return false;
    }

    public function params(ValidationContext $context): array
    {
        return [...parent::params($context), 'suffixes' => $this->suffixes];
    }
}
