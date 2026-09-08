<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Unit\Authentication;

use PHPdot\Iam\Authentication\AuthenticationContext;
use PHPdot\Iam\Authentication\Contract\AuthenticationPolicyInterface;
use PHPdot\Iam\Authentication\RequirementSet;

/**
 * Configurable AuthenticationPolicyInterface for engine tests: returns the next
 * RequirementSet, or "none" when unset. When $scripted is non-empty it answers
 * each call with the next scripted set (falling back to $next), and records
 * every context it was consulted with so re-consultation and attribute merging
 * can be asserted.
 */
final class FakePolicy implements AuthenticationPolicyInterface
{
    public null|RequirementSet $next = null;

    /**
     * @var list<RequirementSet|null>
     */
    public array $scripted = [];

    /**
     * @var list<AuthenticationContext>
     */
    public array $seen = [];

    private int $calls = 0;

    public function requirementsFor(AuthenticationContext $context): RequirementSet
    {
        $this->seen[] = $context;

        if (isset($this->scripted[$this->calls])) {
            $scripted = $this->scripted[$this->calls];
            $this->calls++;

            return $scripted ?? RequirementSet::none();
        }

        $this->calls++;

        return $this->next ?? RequirementSet::none();
    }
}
