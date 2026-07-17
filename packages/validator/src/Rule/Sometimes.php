<?php

declare(strict_types=1);

/**
 * Marker rule. When present in a field's rule list, the entire chain is
 * skipped if the field is absent from the payload. Has no effect when the
 * field is present (always passes — other rules in the chain run).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Sometimes extends Rule
{
    public function passes(mixed $value, ValidationContext $context): bool
    {
        return true;
    }
}
