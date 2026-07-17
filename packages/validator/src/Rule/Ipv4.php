<?php

declare(strict_types=1);

/**
 * The value must be a valid IPv4 address.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Ipv4 extends Rule
{
    public function passes(mixed $value, ValidationContext $context): bool
    {
        return is_string($value)
            && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }
}
