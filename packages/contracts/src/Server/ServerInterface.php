<?php

declare(strict_types=1);

/**
 * Runs a PSR-15 request handler under a server runtime.
 *
 * The single entry point shared by every PHPdot server adapter: capture the
 * request from the runtime, pass it to the handler, emit the response.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Contracts\Server;

use Psr\Http\Server\RequestHandlerInterface;

interface ServerInterface
{
    /**
     * Serve requests with the given PSR-15 handler.
     *
     * @param RequestHandlerInterface $handler The application's PSR-15 handler
     *
     * @return void
     */
    public function serve(RequestHandlerInterface $handler): void;
}
