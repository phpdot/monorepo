<?php

declare(strict_types=1);

/**
 * MCP — the endpoint any client speaks to, ours included.
 *
 * Thin by construction: it resolves the actor behind the request and hands the
 * gateway a question. What the caller may see and run is decided by the registry
 * against that actor, not here.
 *
 * Reaching the endpoint is not the grant — the TOOL LIST is. An actor whose
 * resolver answers null is refused outright, and an actor with no tool
 * permissions connects successfully and is told the server has no tools, which
 * is the truthful answer and a far better one than a 403 that cannot say why.
 *
 * The host mounts this under its own URL with its own access middleware and its
 * own conventions — three verbs on one path:
 *
 *   POST   the messages; GET the stream; DELETE the session's end.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mcp\Server;

use PHPdot\Container\Attribute\Scoped;
use PHPdot\Mcp\Contract\ActorResolverInterface;
use PHPdot\Mcp\Exception\ConfigurationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Scoped]
final readonly class McpEndpoint implements RequestHandlerInterface
{
    public function __construct(
        private ActorResolverInterface $resolver,
        private McpGateway $gateway,
    ) {}

    /**
     * One MCP message, answered as the actor behind the request.
     *
     * @param ServerRequestInterface $request The current request
     *
     * @throws ConfigurationException If no actor is resolvable behind the request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = $this->resolver->resolve($request);

        if ($actor === null) {
            throw ConfigurationException::noActor();
        }

        return $this->gateway->handle($request, $actor);
    }
}
