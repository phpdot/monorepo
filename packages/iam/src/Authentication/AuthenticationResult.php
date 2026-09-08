<?php

declare(strict_types=1);

/**
 * The outcome of a stage or the engine.
 *
 * - authenticated(identity): passed; the verified identity is carried.
 * - pending(pendingAuth): identified, but further proofs are required — the
 *   PendingAuth carries the identity, the ordered requirements, and progress.
 * - failed(reason): verification failed.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication;

use PHPdot\Iam\Authentication\Enums\AuthenticationStatus;
use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;

final class AuthenticationResult
{
    private function __construct(
        public readonly AuthenticationStatus $status,
        public readonly null|IdentityInterface $identity,
        public readonly null|string $reason,
        public readonly null|PendingAuth $pending = null,
    ) {}

    public static function authenticated(IdentityInterface $identity): self
    {
        return new self(AuthenticationStatus::Authenticated, $identity, null);
    }

    public static function pending(PendingAuth $pending): self
    {
        return new self(AuthenticationStatus::Pending, null, null, $pending);
    }

    public static function failed(string $reason): self
    {
        return new self(AuthenticationStatus::Failed, null, $reason);
    }

    public function isAuthenticated(): bool
    {
        return $this->status === AuthenticationStatus::Authenticated;
    }

    public function isPending(): bool
    {
        return $this->status === AuthenticationStatus::Pending;
    }

    public function isFailed(): bool
    {
        return $this->status === AuthenticationStatus::Failed;
    }

    public function pendingAuth(): null|PendingAuth
    {
        return $this->pending;
    }
}
