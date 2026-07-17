<?php

declare(strict_types=1);

/**
 * Base class for all rules. Provides immutable `withError()` and a default
 * `params()` carrying the field name. Subclasses implement `passes()`.
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
    private ?ErrorCodeInterface $errorCode = null;

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

    final public function withError(ErrorCodeInterface $code): static
    {
        $clone = clone $this;
        $clone->errorCode = $code;

        return $clone;
    }

    final public function code(): ?ErrorCodeInterface
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
}
