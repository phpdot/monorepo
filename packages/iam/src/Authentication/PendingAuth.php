<?php

declare(strict_types=1);

/**
 * Half-finished authentication carried between requests: the verified identity,
 * the full ordered RequirementSet the policy demanded, and how many of those are
 * satisfied so far (the cursor).
 *
 * Lives entirely inside authentication — never visible to authorization or to
 * AuthenticatorInterface::current() — and is cleared once every requirement is met. An
 * empty requirement set never becomes a PendingAuth: that case completes
 * immediately.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication;

use DateTimeImmutable;
use PHPdot\Iam\Exception\InvalidRequirementException;
use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;

final readonly class PendingAuth
{
    /**
     * @param null|DateTimeImmutable $expiresAt Hard deadline set at begin; a resume after it fails closed
     * @param list<string> $provenFactors Every factor proven so far, the identifying one first — carried as
     *                                    state because the remaining requirements shrink as factors are satisfied
     * @param array<string, mixed> $attributes Context snapshot taken at the fresh attempt, kept to resume safely
     */
    public function __construct(
        public IdentityInterface $identity,
        public RequirementSet $requirements,
        public int $satisfied,
        public array $attributes = [],
        public null|DateTimeImmutable $expiresAt = null,
        public array $provenFactors = [],
    ) {
        if ($requirements->isEmpty()) {
            throw new InvalidRequirementException(
                'PendingAuth requires a non-empty RequirementSet; an empty set completes immediately.',
            );
        }

        if ($satisfied < 0 || $satisfied > $requirements->count()) {
            throw new InvalidRequirementException(
                "PendingAuth cursor {$satisfied} is out of bounds (0..{$requirements->count()}).",
            );
        }
    }

    /**
     * The next requirement to satisfy (valid while not complete).
     */
    public function current(): Requirement
    {
        return $this->requirements->at($this->satisfied);
    }

    public function isComplete(): bool
    {
        return $this->satisfied >= $this->requirements->count();
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $this->expiresAt !== null && $now >= $this->expiresAt;
    }

    /**
     * A copy advanced one step — the current requirement having just been satisfied.
     */
    public function advance(): self
    {
        return new self(
            $this->identity,
            $this->requirements,
            $this->satisfied + 1,
            $this->attributes,
            $this->expiresAt,
            $this->provenFactors,
        );
    }
}
