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
     * @param ErrorCodeInterface $code
     *
     * @return static
     */
    public function withError(ErrorCodeInterface $code): static;

    /**
     * The bound error code, or null if `withError()` was never called.
     *
     * @return ?ErrorCodeInterface
     */
    public function code(): ?ErrorCodeInterface;

    /**
     * ICU interpolation params merged into the resulting ErrorEntry.
     *
     * Defaults to `['field' => $context->field()]`. Rules with extra params
     * (`min`, `max`, `other`, etc.) override this.
     *
     * @param ValidationContext $context
     *
     * @return array<string, mixed>
     */
    public function params(ValidationContext $context): array;
}
