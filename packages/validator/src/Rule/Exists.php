<?php

declare(strict_types=1);

/**
 * The value MUST exist according to the supplied resolver.
 *
 * The resolver is a callable `fn(mixed $value, ValidationContext $ctx): bool`
 * that returns `true` if the value exists.
 *
 * ```php
 * (new Exists(fn(mixed $v): bool => $orgRepo->find((string) $v) !== null))
 * ->withError(OrgErrorCode::OrgNotFound)
 * ```
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use Closure;
use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Exists extends Rule
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
        return ($this->resolver)($value, $context) === true;
    }
}
