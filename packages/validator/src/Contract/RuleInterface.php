<?php

declare(strict_types=1);

/**
 * Single validation rule.
 *
 * Rules are immutable. `withError()` returns a new instance carrying the
 * developer-supplied error code. Rules without a code throw at validation
 * time — this package is strict by design.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Contract;

use PHPdot\Error\ErrorCodeInterface;
use PHPdot\Validator\ValidationContext;

interface RuleInterface
{
    /**
     * Whether the value satisfies the rule.
     *
     * @param mixed $value
     * @param ValidationContext $context
     *
     * @return bool
     */
    public function passes(mixed $value, ValidationContext $context): bool;

    /**
     * Return a new instance bound to the given error code.
     *
     * `$params` are ICU arguments for the message and OVERRIDE the rule's own,
     * so a call site can state the bound its translation interpolates instead
     * of the catalog hard-coding a number.
     *
     * @param ErrorCodeInterface $code
     * @param array<string, mixed> $params ICU params, merged over `params()`
     *
     * @return static
     */
    public function withError(ErrorCodeInterface $code, array $params = []): static;

    /**
     * The params `withError()` was given, or `[]`.
     *
     * @return array<string, mixed>
     */
    public function errorParams(): array;

    /**
     * The bound error code, or null if `withError()` was never called.
     *
     * @return ?ErrorCodeInterface
     */
    public function code(): null|ErrorCodeInterface;

    /**
     * ICU interpolation params merged into the resulting ErrorEntry.
     *
     * Defaults to `['field' => $context->field()]`. Rules with extra params
     * (`min`, `max`, `other`, etc.) override this; `errorParams()` is merged
     * over the result.
     *
     * @param ValidationContext $context
     *
     * @return array<string, mixed>
     */
    public function params(ValidationContext $context): array;
}
