<?php

declare(strict_types=1);

/**
 * Outbound connection sender — the narrow seam to reach connected clients.
 *
 * A server transport implements it; a real-time layer (rooms, presence,
 * broadcast) consumes it without naming a concrete server type.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Contracts\Server;

interface ConnectionSenderInterface
{
    /**
     * Push a WebSocket text frame to one connection.
     *
     * @param int $fd The connection file descriptor.
     * @param string $frame The frame payload (already encoded).
     *
     * @return bool False if the fd is not an established WebSocket client.
     */
    public function pushWs(int $fd, string $frame): bool;

    /**
     * Send a WebSocket PING control frame to one connection (heartbeat liveness probe).
     * A live client's WS stack auto-replies with a PONG; silence over time means dead.
     *
     * @param int $fd The connection file descriptor.
     *
     * @return bool False if the fd is not an established WebSocket client.
     */
    public function pingWs(int $fd): bool;

    /**
     * Gracefully close a connection with a WebSocket close code + reason.
     *
     * @param int $fd The connection file descriptor.
     * @param int $code The WebSocket close code (default 1000, normal closure).
     * @param string $reason The close reason.
     *
     * @return bool
     */
    public function disconnect(int $fd, int $code = 1000, string $reason = ''): bool;

    /**
     * Whether the fd is a currently-connected client.
     *
     * @param int $fd The connection file descriptor.
     *
     * @return bool
     */
    public function exists(int $fd): bool;
}
