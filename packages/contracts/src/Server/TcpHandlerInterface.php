<?php

declare(strict_types=1);

/**
 * Handles raw TCP connection lifecycle.
 *
 * Detected on the handler passed to `Server::serve()`; the TCP transport
 * delegates connection events to it. Connections are identified by their
 * Swoole file descriptor.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Contracts\Server;

interface TcpHandlerInterface
{
    /**
     * Handle a new TCP connection.
     *
     * @param int $fd Connection file descriptor
     *
     * @return void
     */
    public function handleTcpConnect(int $fd): void;

    /**
     * Handle incoming data on a TCP connection.
     *
     * @param int $fd Connection file descriptor
     * @param string $data Received payload, framed per the TcpServer's framing mode
     *
     * @return void
     */
    public function handleTcpReceive(int $fd, string $data): void;

    /**
     * Handle a TCP connection closing.
     *
     * @param int $fd Connection file descriptor
     *
     * @return void
     */
    public function handleTcpClose(int $fd): void;
}
