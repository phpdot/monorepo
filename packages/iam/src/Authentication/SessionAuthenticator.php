<?php

declare(strict_types=1);

/**
 * Session-backed authenticator. Stores the logged-in identity's id + type in
 * the session; reads it back via the IdentityReconstructorInterface; clears on
 * logout — together with any half-finished staged login still parked in the
 * pending store, so a logout ends the whole authentication, never just its
 * completed half.
 *
 * Session fixation defense: regenerate the session id on authenticate,
 * DESTROYING the pre-authentication record (it can carry pending-auth
 * residue); invalidate on deauthenticate. The identity is stored as id + type
 * only and rebuilt on read — never serialized — so a tampered session cannot
 * inject an object.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication;

use PHPdot\Container\Attribute\Binds;
use PHPdot\Container\Attribute\Scoped;
use PHPdot\Contracts\Session\SessionInterface;
use PHPdot\Iam\Authentication\Contract\AuthenticatorInterface;
use PHPdot\Iam\Authentication\Contract\IdentityReconstructorInterface;
use PHPdot\Iam\Authentication\Contract\IdentityRevalidatorInterface;
use PHPdot\Iam\Authentication\Contract\PendingAuthStoreInterface;
use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;

#[Scoped]
#[Binds(AuthenticatorInterface::class)]
final readonly class SessionAuthenticator implements AuthenticatorInterface
{
    private const string ID_KEY = '_auth.id';

    private const string TYPE_KEY = '_auth.type';

    public function __construct(
        private SessionInterface $session,
        private IdentityReconstructorInterface $reconstructor,
        private PendingAuthStoreInterface $pendingStore,
        private IdentityRevalidatorInterface $revalidator = new TrustingRevalidator(),
    ) {}

    public function authenticate(IdentityInterface $identity): void
    {
        $this->session->regenerate(destroy: true);
        $this->session->set(self::ID_KEY, $identity->id());
        $this->session->set(self::TYPE_KEY, $identity->type());
    }

    public function deauthenticate(): void
    {
        $this->pendingStore->clear();
        $this->session->remove(self::ID_KEY);
        $this->session->remove(self::TYPE_KEY);
        $this->session->invalidate();
    }

    public function current(): null|IdentityInterface
    {
        if (!$this->session->has(self::ID_KEY) || !$this->session->has(self::TYPE_KEY)) {
            return null;
        }

        $id = $this->session->get(self::ID_KEY);
        $type = $this->session->get(self::TYPE_KEY);

        if (!is_string($type)) {
            return null;
        }

        if ($id !== null && !is_int($id) && !is_string($id)) {
            return null;
        }

        $identity = $this->reconstructor->reconstruct($id, $type);

        if ($identity === null) {
            return null;
        }

        return $this->revalidator->revalidate($identity) ? $identity : null;
    }
}
