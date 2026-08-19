<?php

declare(strict_types=1);

/**
 * Pipeline — the PSR-15 dispatcher: a middleware list around a final handler.
 *
 * Middlewares run in list order (the first is outermost); each may
 * short-circuit by returning a response without delegating, or pass on via
 * the handler it receives. The pipeline itself IS a RequestHandlerInterface,
 * so pipelines nest and any transport can serve one directly.
 *
 * Deliberately STATELESS per call: the walk position travels through call
 * arguments, never instance state, so one shared Pipeline instance serves
 * concurrent coroutines safely — a dispatcher holding a mutable index would
 * interleave requests under Swoole.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\HttpMiddleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class Pipeline implements RequestHandlerInterface
{
    /**
     * Create a pipeline over an ordered middleware list and its final handler.
     *
     * @param list<MiddlewareInterface> $middlewares Outermost first
     * @param RequestHandlerInterface $handler Runs when every middleware delegates
     */
    public function __construct(
        private array $middlewares,
        private RequestHandlerInterface $handler,
    ) {}

    /**
     * @inheritDoc
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->step(0)->handle($request);
    }

    /**
     * The handler for position $index: the middleware there wired to the rest
     * of the chain, or the final handler when the list is exhausted.
     *
     * @param int $index Position in the middleware list
     *
     * @return RequestHandlerInterface
     */
    private function step(int $index): RequestHandlerInterface
    {
        $middleware = $this->middlewares[$index] ?? null;

        if ($middleware === null) {
            return $this->handler;
        }

        $next = $this->step($index + 1);

        return new class ($middleware, $next) implements RequestHandlerInterface {
            public function __construct(
                private readonly MiddlewareInterface $middleware,
                private readonly RequestHandlerInterface $next,
            ) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->middleware->process($request, $this->next);
            }
        };
    }
}
