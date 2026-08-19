<?php

declare(strict_types=1);

/**
 * Base class for all rules. Provides immutable `withError()`, a default
 * `params()` carrying the field name, and `errorParams()` holding whatever the
 * call site passed. Subclasses implement `passes()`.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator;

use Closure;
use PHPdot\Error\ErrorCodeInterface;
use PHPdot\Validator\Contract\RuleInterface;
use PHPdot\Validator\Rule\ClosureRule;

abstract class Rule implements RuleInterface
{
    private null|ErrorCodeInterface $errorCode = null;

    /**
     * @var array<string, mixed> Extra ICU params supplied at the call site
     */
    private array $errorParams = [];

    /**
     * Build an ad-hoc rule from a closure that returns whether the value passes.
     *
     * @param Closure $check Receives the value and context, returns a bool
     *
     * @return ClosureRule
     */
    final public static function closure(Closure $check): ClosureRule
    {
        return new ClosureRule($check);
    }

    /**
     * Whether `$value` is "empty" by validator semantics: `null`, `[]`, or a
     * string that contains only whitespace.
     *
     * `0`, `'0'`, and `false` are NOT empty — they are real submitted values.
     * `'   '` (whitespace-only) IS empty — the user typed nothing meaningful.
     *
     * @param mixed $value
     *
     * @return bool
     */
    final public static function isEmpty(mixed $value): bool
    {
        if ($value === null || $value === []) {
            return true;
        }

        return is_string($value) && trim($value) === '';
    }

    /**
     * @param array<string, mixed> $params ICU params for the message, merged into `params()`
     */
    final public function withError(ErrorCodeInterface $code, array $params = []): static
    {
        $clone = clone $this;
        $clone->errorCode = $code;
        $clone->errorParams = $params;

        return $clone;
    }

    final public function code(): null|ErrorCodeInterface
    {
        return $this->errorCode;
    }

    abstract public function passes(mixed $value, ValidationContext $context): bool;

    /**
     * @return array<string, mixed>
     */
    public function params(ValidationContext $context): array
    {
        return ['field' => $context->field()];
    }

    /**
     * What `withError()` was given, merged over `params()` by the validator.
     *
     * Kept out of `params()`: a subclass spreads it FIRST, so anything the base
     * added there would lose to the rule's own.
     *
     * @return array<string, mixed>
     */
    final public function errorParams(): array
    {
        return $this->errorParams;
    }
}
