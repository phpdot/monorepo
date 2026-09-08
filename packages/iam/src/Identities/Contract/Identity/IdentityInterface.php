<?php

declare(strict_types=1);

/**
 * The lightweight "who" of an authenticated actor: a stable identifier and a
 * type that names how to reconstruct and reason about it. Carries no profile
 * data (name, email, …) — that is the job of the details layer.
 *
 * `id()` is intentionally string|int|null: users have a real id, the guest has
 * none, and a future anonymous/deferred identity may carry a string handle.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Identities\Contract\Identity;

interface IdentityInterface
{
    /**
     * The actor's stable identifier, or null when there is none (e.g. guest).
     *
     * @return int|string|null
     */
    public function id(): int|string|null;

    /**
     * The identity kind — 'user', 'guest', 'cli', … Used by the
     * IdentityReconstructorInterface to rebuild the concrete type from stored id+type,
     * and by the subject resolver to mint a Casbin subject string.
     *
     * @return string
     */
    public function type(): string;
}
