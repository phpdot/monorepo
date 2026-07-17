<?php

declare(strict_types=1);

/**
 * The value must be a valid URL (FILTER_VALIDATE_URL).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Url extends Rule
{
    public function passes(mixed $value, ValidationContext $context): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
}
