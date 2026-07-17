<?php

declare(strict_types=1);

/**
 * Handles Server-Sent Events (SSE) requests.
 *
 * Implemented by routers that support SSE dispatch; servers detect it and
 * delegate SSE requests before normal HTTP handling.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Contracts\Server;

use Closure;
use Psr\Http\Message\ServerRequestInterface;

interface SseHandlerInterface
{
    /**
     * Handle an SSE request.
     *
     * Returns true if a route matched and the stream was handled,
     * false to fall through to normal HTTP dispatch.
     *
     * @param ServerRequestInterface $request The incoming request
     * @param Closure(string): bool $write Write SSE data to the stream (returns false on client disconnect)
     * @param Closure(): void $close Close the stream
     *
     * @return bool
     */
    public function handleSse(
        ServerRequestInterface $request,
        Closure $write,
        Closure $close,
    ): bool;
}
