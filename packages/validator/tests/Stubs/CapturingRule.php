<?php

declare(strict_types=1);

namespace PHPdot\Validator\Tests\Stubs;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

/**
 * Rule that always fails and remembers what context it was called with.
 * Used by tests to assert params + context wiring.
 */
final class CapturingRule extends Rule
{
    /** @var array<string, mixed> */
    public array $extraParams;

    public mixed $lastValue = null;

    public ?ValidationContext $lastContext = null;

    /**
     * @param array<string, mixed> $extraParams
     */
    public function __construct(array $extraParams = [])
    {
        $this->extraParams = $extraParams;
    }

    public function passes(mixed $value, ValidationContext $context): bool
    {
        $this->lastValue = $value;
        $this->lastContext = $context;

        return false;
    }

    public function params(ValidationContext $context): array
    {
        return [...parent::params($context), ...$this->extraParams];
    }
}
