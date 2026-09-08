<?php

declare(strict_types=1);

/**
 * Turns one HTTP request into the actor behind it — or into nobody.
 *
 * The host binds this; the package ships no default on purpose. A missing
 * binding fails loudly at resolution, and a resolver answering null refuses the
 * request: the tool list is the grant, and a grant needs someone to hold it.
 * Anonymous callers are never served.
 *
 * The request is passed so a host may derive its actor from whatever the
 * surrounding pipeline attached — an identity context, a session, a header.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mcp\Contract;

use Psr\Http\Message\ServerRequestInterface;

interface ActorResolverInterface
{
    /**
     * The actor behind the request, or null when there is none.
     *
     * @param ServerRequestInterface $request The request that reached the endpoint
     *
     * @return ToolActorInterface|null
     */
    public function resolve(ServerRequestInterface $request): ToolActorInterface|null;
}
