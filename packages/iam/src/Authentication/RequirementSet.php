<?php

declare(strict_types=1);

/**
 * The ordered set of proofs the policy still requires, in the order they must be
 * satisfied.
 *
 * Owned by the application's AuthenticationPolicyInterface — the engine executes this
 * order and never reorders or invents requirements. Order is itself a security
 * decision (cheapest / most phishing-resistant first) and may vary by context,
 * which is why it lives in the policy. An empty set means "nothing more
 * required — issue the session".
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication;

use PHPdot\Iam\Exception\InvalidRequirementException;

final readonly class RequirementSet
{
    /**
     * @param list<Requirement> $requirements Ordered; satisfied front-to-back.
     */
    public function __construct(
        public array $requirements,
    ) {}

    public static function none(): self
    {
        return new self([]);
    }

    public function isEmpty(): bool
    {
        return $this->requirements === [];
    }

    public function count(): int
    {
        return count($this->requirements);
    }

    /**
     * The requirement at $index.
     *
     * @throws InvalidRequirementException if the index is outside the set
     */
    public function at(int $index): Requirement
    {
        return $this->requirements[$index]
            ?? throw new InvalidRequirementException("No requirement at index {$index}.");
    }
}
