<?php

declare(strict_types=1);

/**
 * The value must be an array with no duplicate values (strict comparison).
 *
 * Useful for tag lists, multi-select inputs, or any field that holds a
 * sequence where repeats are meaningless or invalid.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Distinct extends Rule
{
    public function passes(mixed $value, ValidationContext $context): bool
    {
        if (!is_array($value)) {
            return false;
        }

        $seen = [];

        foreach ($value as $item) {
            foreach ($seen as $existing) {
                if ($item === $existing) {
                    return false;
                }
            }
            $seen[] = $item;
        }

        return true;
    }
}
