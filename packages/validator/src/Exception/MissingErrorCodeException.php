<?php

declare(strict_types=1);

/**
 * Thrown when a rule fails but no error code was bound via `withError()`.
 *
 * `phpdot/validator` is strict by design — every failing rule must produce a
 * structured error code. The fix is always to call `->withError($code)` on
 * the rule instance.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Exception;

final class MissingErrorCodeException extends ValidatorException
{
    /**
     * __construct.
     *
     * @param string $field
     * @param string $ruleClass
     */
    public function __construct(
        public readonly string $field,
        public readonly string $ruleClass,
    ) {
        parent::__construct(sprintf(
            'Rule %s failed for field "%s" without a bound error code. Call ->withError($code) on the rule instance.',
            $ruleClass,
            $field,
        ));
    }
}
