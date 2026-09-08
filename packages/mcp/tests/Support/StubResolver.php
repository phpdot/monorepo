<?php

declare(strict_types=1);

namespace PHPdot\Mcp\Tests\Support;

use PHPdot\Mcp\Contract\ActorResolverInterface;
use PHPdot\Mcp\Contract\ToolActorInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A resolver that answers what it was handed — including null, which is the
 * refusal the endpoint must prove.
 */
final class StubResolver implements ActorResolverInterface
{
    public function __construct(private readonly ToolActorInterface|null $actor) {}

    /**
     * @param ServerRequestInterface $request The request that reached the endpoint
     *
     * @return ToolActorInterface|null
     */
    public function resolve(ServerRequestInterface $request): ToolActorInterface|null
    {
        return $this->actor;
    }
}
