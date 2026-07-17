<?php

declare(strict_types=1);

/**
 * The value must contain only Unicode letters, digits, hyphens, and underscores.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class AlphaDash extends Rule
{
    public function passes(mixed $value, ValidationContext $context): bool
    {
        return is_string($value) && preg_match('/^[\p{L}\p{N}_-]+$/u', $value) === 1;
    }
}
