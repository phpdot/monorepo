<?php

declare(strict_types=1);

/**
 * Handles WebSocket lifecycle events.
 *
 * Implemented by routers that support WebSocket dispatch; servers detect it
 * and delegate WebSocket events.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Contracts\Server;

use Closure;
use Psr\Http\Message\ServerRequestInterface;

interface WebSocketHandlerInterface
{
    /**
     * Handle a new WebSocket connection.
     *
     * Called after the protocol upgrade completes. Returns true if a route
     * matched and the connection was accepted, false to reject.
     *
     * @param int $fd Connection file descriptor
     * @param ServerRequestInterface $request The upgrade request
     * @param Closure(string): bool $send Send text frame
     * @param Closure(string): bool $sendBinary Send binary frame
     * @param Closure(int, string): bool $close Close connection
     *
     * @return bool
     */
    public function handleWsOpen(
        int $fd,
        ServerRequestInterface $request,
        Closure $send,
        Closure $sendBinary,
        Closure $close,
    ): bool;

    /**
     * Handle an incoming WebSocket message.
     *
     * @param int $fd Connection file descriptor
     * @param string $data Message payload
     * @param int $opcode WebSocket opcode (1 = text, 2 = binary)
     *
     * @return void
     */
    public function handleWsMessage(int $fd, string $data, int $opcode): void;

    /**
     * Handle a WebSocket connection close.
     *
     * @param int $fd Connection file descriptor
     * @param int $code Close status code
     * @param string $reason Close reason
     *
     * @return void
     */
    public function handleWsClose(int $fd, int $code, string $reason): void;
}
