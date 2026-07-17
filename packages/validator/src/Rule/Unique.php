<?php

declare(strict_types=1);

/**
 * The value must NOT already exist according to the supplied resolver.
 *
 * The resolver is a callable `fn(mixed $value, ValidationContext $ctx): bool`
 * that returns `true` if the value already exists. Keeping the lookup as a
 * callable means the package has no DB coupling — wire any storage:
 *
 * ```php
 * (new Unique(fn(mixed $v): bool => $userRepo->existsByEmail((string) $v)))
 * ->withError(UserErrorCode::EmailTaken)
 * ```
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use Closure;
use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Unique extends Rule
{
    /**
     * __construct.
     *
     * @param Closure $resolver
     */
    public function __construct(
        private readonly Closure $resolver,
    ) {}

    public function passes(mixed $value, ValidationContext $context): bool
    {
        return ($this->resolver)($value, $context) === false;
    }
}
