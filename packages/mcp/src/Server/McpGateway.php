<?php

declare(strict_types=1);

/**
 * The boundary to the official MCP SDK — one of the two files in this package
 * importing Mcp\*, and the only place an SDK failure is allowed to surface from.
 *
 * BUILT PER REQUEST, not once per worker, and that is the whole design. The tool
 * list this server advertises is the CALLER's list: {@see ToolRegistry::forActor()}
 * answers only what the actor may run, so an operator holding one app's keys is
 * never told the others exist. A worker-level server would have to advertise every
 * tool to everyone and check permission only on call — which leaks the vocabulary,
 * and hands a model names it will try. Building a Server is object construction
 * over an already-discovered array; correctness is worth it.
 *
 * TOOL EXECUTION DOES NOT GO THROUGH THE HANDLERS REGISTERED HERE. The closure
 * passed to `addTool()` exists to satisfy the builder's signature and is never
 * called: {@see ToolReferenceHandler} intercepts every invocation and routes it
 * back into the registry, which re-checks the permission. If one of these closures
 * ever runs, the seam has moved and it says so by throwing.
 *
 * The SDK's default transport middleware is disabled: host validation,
 * authentication and identity belong to the surrounding pipeline, which has
 * already run by the time a request reaches here.
 *
 * Sessions live in a PSR-16 store that MUST be shared across workers — a
 * per-worker store loses the session the moment Swoole dispatches the next
 * request to a sibling. The host may bind a dedicated
 * {@see McpSessionStoreInterface} (a cluster store over its pooled connection);
 * unbound, the gateway builds the FileDriver default over the configured
 * session path. Either way the store is the endpoint's own and never the
 * host's shared cache binding: a host that clears its cache would take every
 * live MCP session with it, which is exactly why the seam is a distinct type
 * and not plain CacheInterface.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mcp\Server;

use Mcp\Schema\ToolAnnotations;
use Mcp\Server;
use Mcp\Server\Session\Psr16SessionStore;
use Mcp\Server\Transport\StreamableHttpTransport;
use PHPdot\Cache\Driver\FileDriver;
use PHPdot\Cache\Store;
use PHPdot\Container\Attribute\Singleton;
use PHPdot\Http\Factory\ResponseFactory;
use PHPdot\Mcp\Contract\McpSessionStoreInterface;
use PHPdot\Mcp\Contract\ToolActorInterface;
use PHPdot\Mcp\Exception\ConfigurationException;
use PHPdot\Mcp\Exception\GatewayException;
use PHPdot\Mcp\Tool\ToolRegistry;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

#[Singleton]
final readonly class McpGateway
{
    public function __construct(
        private ResponseFactory $factory,
        private ToolRegistry $registry,
        private McpConfig $config,
        private null|McpSessionStoreInterface $store = null,
    ) {
        if ($this->store === null && trim($this->config->sessionPath) === '') {
            throw ConfigurationException::missing(
                'the session store path (mcp.sessionPath) — or a bound McpSessionStoreInterface',
            );
        }

        Psr17DiscoveryStrategy::register();
    }

    /**
     * Answer one MCP request as this actor.
     *
     * @param ServerRequestInterface $request The current request
     * @param ToolActorInterface $actor Who is asking
     *
     * @throws GatewayException If the SDK fails outside its own protocol error handling
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request, ToolActorInterface $actor): ResponseInterface
    {
        /*
         * REWIND FIRST. The SDK's transport reads the body from its CURRENT position to
         * EOF, and a request that has already reached this point has usually been read
         * once on the way — so the stream sits at the end and the transport sees an
         * empty body, which it reports as `-32700 Syntax error`. That error names the
         * JSON parser and says nothing about the position of a file pointer, which is
         * why it is worth a paragraph rather than a one-line fix nobody can explain.
         */
        $body = $request->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        $transport = new StreamableHttpTransport(
            request: $request,
            responseFactory: $this->factory,
            streamFactory: $this->factory,
            middleware: [],
        );

        try {
            return $this->server($actor)->run($transport);
        } catch (Throwable $failure) {
            throw GatewayException::from($failure);
        }
    }

    /**
     * The SDK server, carrying this actor's tools and nobody else's.
     *
     * @param ToolActorInterface $actor Who is asking
     *
     * @return Server
     */
    private function server(ToolActorInterface $actor): Server
    {
        $builder = Server::builder()
            ->setServerInfo($this->config->name, $this->config->version)
            ->setReferenceHandler(new ToolReferenceHandler($this->registry, $actor))
            ->setSession(new Psr16SessionStore(
                $this->store ?? new Store(new FileDriver($this->config->sessionPath)),
                ttl: $this->config->sessionTtl,
            ));

        foreach ($this->registry->forActor($actor) as $tool) {
            $builder->addTool(
                static function () use ($tool): never {
                    throw GatewayException::handlerBypassed($tool->name);
                },
                $tool->name,
                title: $tool->name,
                description: $tool->description,
                annotations: new ToolAnnotations(
                    readOnlyHint: $tool->readOnly,
                    destructiveHint: $tool->destructive,
                    idempotentHint: $tool->idempotent,
                ),
                inputSchema: $tool->parameters,
            );
        }

        return $builder->build();
    }
}
