<?php

declare(strict_types=1);

/**
 * Wrap a closure as a one-shot rule for inline business logic.
 *
 * Construct via the factory `Rule::closure(...)`:
 *
 * ```php
 * Rule::closure(function (mixed $value, ValidationContext $ctx): bool {
 * $start = $ctx->value('start_date');
 * return strtotime($value) > strtotime($start);
 * })->withError(UserErrorCode::EndDateBeforeStart);
 * ```
 *
 * The closure returns a boolean. The error code (and message) come from the
 * developer's enum via `withError()` — same as every other rule.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use Closure;
use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class ClosureRule extends Rule
{
    /**
     * __construct.
     *
     * @param Closure $check
     */
    public function __construct(
        private readonly Closure $check,
    ) {}

    public function passes(mixed $value, ValidationContext $context): bool
    {
        return ($this->check)($value, $context) === true;
    }
}
