<?php

declare(strict_types=1);

/**
 * Resolves the tool actor from iam's scoped Authorizer — the binding a phpdot
 * host makes to turn the endpoint on:
 *
 *   ActorResolverInterface::class => IamActorResolver::class
 *
 * An Authorizer with no identity answers null, and the endpoint refuses: a
 * request without an identity is never served, matching iam's own fail-closed
 * posture everywhere else.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mcp\Bridge;

use PHPdot\Container\Attribute\Scoped;
use PHPdot\Iam\Authorization\Contract\AuthorizerInterface;
use PHPdot\Mcp\Contract\ActorResolverInterface;
use PHPdot\Mcp\Contract\ToolActorInterface;
use Psr\Http\Message\ServerRequestInterface;

#[Scoped]
final readonly class IamActorResolver implements ActorResolverInterface
{
    public function __construct(private AuthorizerInterface $authorizer) {}

    /**
     * @param ServerRequestInterface $request The request that reached the endpoint
     *
     * @return ToolActorInterface|null
     */
    public function resolve(ServerRequestInterface $request): ToolActorInterface|null
    {
        if ($this->authorizer->identity() === null) {
            return null;
        }

        return new IamActor($this->authorizer);
    }
}
