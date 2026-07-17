<?php

declare(strict_types=1);

/**
 * Thrown when a rule list contains a value that is not a `RuleInterface` instance.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Exception;

final class InvalidRuleException extends ValidatorException
{
    /**
     * __construct.
     *
     * @param string $field
     * @param string $actualType
     */
    public function __construct(
        public readonly string $field,
        public readonly string $actualType,
    ) {
        parent::__construct(sprintf(
            'Rule list for field "%s" must contain RuleInterface instances; got %s.',
            $field,
            $actualType,
        ));
    }
}
