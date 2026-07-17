<?php

declare(strict_types=1);

/**
 * The value must contain only Unicode letters.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Alpha extends Rule
{
    public function passes(mixed $value, ValidationContext $context): bool
    {
        return is_string($value) && preg_match('/^\p{L}+$/u', $value) === 1;
    }
}
