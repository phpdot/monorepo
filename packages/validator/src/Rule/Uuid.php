<?php

declare(strict_types=1);

/**
 * The value must be a valid UUID (any version, hyphenated lowercase or uppercase).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Uuid extends Rule
{
    private const PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function passes(mixed $value, ValidationContext $context): bool
    {
        return is_string($value) && preg_match(self::PATTERN, $value) === 1;
    }
}
