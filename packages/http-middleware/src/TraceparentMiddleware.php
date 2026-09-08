<?php

declare(strict_types=1);

/**
 * Traceparent Middleware
 *
 * Returns the request's W3C `traceparent` on the response, so a client that
 * reports a slow or failed request carries the one identifier that resolves it
 * to an exact trace across every channel file.
 *
 * Sits outside the error handler: a 500 is when the identifier matters most, so
 * the synthesized response must carry it too.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\HttpMiddleware;

use PHPdot\Contracts\Logs\TracerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class TraceparentMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly TracerInterface $tracer,
    ) {}

    /**
     * The header is read at ENTRY, before any inner work runs: the active span
     * is then guaranteed to be the span that owns this request. Reading after
     * the handler returns would echo whatever span happens to be current — a
     * child left open at response time (the leak the trace kernel cleans up
     * only later) would hijack the identifier.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $traceparent = $this->tracer->context()->toTraceparent();

        return $handler->handle($request)
            ->withHeader('traceparent', $traceparent);
    }
}
