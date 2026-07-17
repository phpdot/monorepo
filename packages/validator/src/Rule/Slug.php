<?php

declare(strict_types=1);

/**
 * The value must be a URL-friendly slug: lowercase letters, digits, and hyphens.
 *
 * No leading/trailing hyphens. No consecutive hyphens.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Slug extends Rule
{
    public function passes(mixed $value, ValidationContext $context): bool
    {
        return is_string($value) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) === 1;
    }
}
