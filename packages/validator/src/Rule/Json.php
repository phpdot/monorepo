<?php

declare(strict_types=1);

/**
 * The value must be a string containing valid JSON.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Json extends Rule
{
    public function passes(mixed $value, ValidationContext $context): bool
    {
        if (!is_string($value) || $value === '') {
            return false;
        }

        return json_validate($value);
    }
}
