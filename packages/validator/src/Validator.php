<?php

declare(strict_types=1);

/**
 * Runs rules against a payload and accumulates failures into an `ErrorBag`.
 *
 * Holds the bag passed at construction. Each `validate()` call adds entries
 * to that bag and returns it; the returned bag is always the same held
 * instance, so multi-payload validation accumulates naturally:
 *
 *     $v = $factory->create();
 *     $v->validate($userInput,    $userRules);
 *     $v->validate($paymentInput, $paymentRules);
 *     $bag = $v->errors();   // both payloads' errors combined
 *
 * Strict by design: a failing rule without `->withError(...)` throws
 * `MissingErrorCodeException`. Two flow-control rules short-circuit a
 * field's chain — `Sometimes` (skip if absent), `Nullable` (skip if null).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator;

use PHPdot\Error\ErrorBag;
use PHPdot\Validator\Contract\RuleInterface;
use PHPdot\Validator\Exception\InvalidRuleException;
use PHPdot\Validator\Exception\MissingErrorCodeException;
use PHPdot\Validator\Rule\Bail;
use PHPdot\Validator\Rule\Nullable;
use PHPdot\Validator\Rule\Sometimes;

final class Validator
{
    /**
     * __construct.
     *
     * @param ErrorBag $bag
     */
    public function __construct(
        private readonly ErrorBag $bag,
    ) {}

    /**
     * Run rules against `$data`, accumulating failures into the held bag.
     *
     * Returns the held bag — the same instance is returned every call.
     *
     * @param array<string, mixed> $data
     * @param array<string, list<mixed>> $rules Each rule must be a `RuleInterface` instance.
     *
     * @return ErrorBag
     */
    public function validate(array $data, array $rules): ErrorBag
    {
        foreach ($rules as $field => $fieldRules) {
            $this->validateField($field, $fieldRules, $data, $this->bag);
        }

        return $this->bag;
    }

    /**
     * The accumulated error bag — same instance returned by `validate()`.
     *
     * @return ErrorBag
     */
    public function errors(): ErrorBag
    {
        return $this->bag;
    }

    /**
     * Run every rule for one field, collecting failures into the error bag.
     *
     * @param string $field Name of the field to validate
     * @param list<mixed> $fieldRules The rules configured for this field
     * @param array<string, mixed> $data The full input data set
     * @param ErrorBag $bag Collects a structured error per failed rule
     *
     * @return void
     */
    private function validateField(string $field, array $fieldRules, array $data, ErrorBag $bag): void
    {
        $context = new ValidationContext($field, $data);
        $value = $context->value($field);

        $bail = false;
        foreach ($fieldRules as $rule) {
            if ($rule instanceof Bail) {
                $bail = true;
                break;
            }
        }

        foreach ($fieldRules as $rule) {
            if ($rule instanceof Bail) {
                continue;
            }

            if (!$rule instanceof RuleInterface) {
                throw new InvalidRuleException($field, get_debug_type($rule));
            }

            if ($rule instanceof Sometimes && !$context->has($field)) {
                return;
            }

            if ($rule instanceof Nullable && $value === null) {
                return;
            }

            if ($rule->passes($value, $context)) {
                continue;
            }

            $code = $rule->code();

            if ($code === null) {
                throw new MissingErrorCodeException($field, $rule::class);
            }

            $bag->add($code, $field, $rule->params($context));

            if ($bail) {
                return;
            }
        }
    }
}
