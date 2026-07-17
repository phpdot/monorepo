<?php

declare(strict_types=1);

/**
 * Container Dispatcher
 *
 * A PSR-15 handler that resolves the real application handler from the container
 * on every request — inside the worker, after the fork. That late resolution is
 * what lets app code load lazily (so a worker reload picks up edits) and lets
 * per-coroutine scoped services isolate. Serve this, configured with the
 * application handler's service id.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Container\Swoole;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

final class ContainerDispatcher implements RequestHandlerInterface
{
    /**
     * Create a dispatcher resolving the handler from the container per request.
     *
     * @param class-string<RequestHandlerInterface> $handlerId
     * @param ContainerInterface $container
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly string $handlerId,
    ) {}

    /**
     * Resolve the handler for this request from the container and delegate,
     * so every request gets its scope-correct handler instance.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $handler = $this->container->get($this->handlerId);

        if (!$handler instanceof RequestHandlerInterface) {
            throw new RuntimeException(sprintf(
                'The configured handler "%s" must resolve to a %s.',
                $this->handlerId,
                RequestHandlerInterface::class,
            ));
        }

        return $handler->handle($request);
    }
}
