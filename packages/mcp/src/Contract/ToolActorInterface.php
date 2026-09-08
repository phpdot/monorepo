<?php

declare(strict_types=1);

/**
 * Who is asking, reduced to the one question the package ever asks of them.
 *
 * The whole of the package's permission model: a tool requires a key, and the
 * actor either holds it or does not. The host decides what an actor IS — an iam
 * Authorizer, a token's scopes, a hand-rolled set in a test — by binding an
 * {@see ActorResolverInterface}; the package never learns more about identity
 * than this, which is what lets it serve a host with any permission system.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mcp\Contract;

interface ToolActorInterface
{
    /**
     * Whether this actor holds the given permission key.
     *
     * @param string $permission The key a tool declared
     *
     * @return bool
     */
    public function can(string $permission): bool;
}
