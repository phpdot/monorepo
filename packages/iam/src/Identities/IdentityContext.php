<?php

declare(strict_types=1);

/**
 * Per-request holder of the current identity and request details. Scoped: one
 * instance per request, so mutations made by the authenticator/middleware are
 * visible to everything else in that request and leak nowhere else.
 *
 * Written by the authentication middleware (the resolved identity) and the
 * framework's request bootstrap (the request details); read by the authorizer,
 * the views, and anything else that needs "the current actor".
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Identities;

use PHPdot\Container\Attribute\Scoped;
use PHPdot\Iam\Identities\Contract\Context\RequestDetailsInterface;
use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;

#[Scoped]
final class IdentityContext
{
    private null|IdentityInterface $current = null;

    private null|RequestDetailsInterface $request = null;

    public function current(): null|IdentityInterface
    {
        return $this->current;
    }

    public function setCurrent(null|IdentityInterface $identity): void
    {
        $this->current = $identity;
    }

    public function request(): null|RequestDetailsInterface
    {
        return $this->request;
    }

    public function setRequest(null|RequestDetailsInterface $request): void
    {
        $this->request = $request;
    }
}
