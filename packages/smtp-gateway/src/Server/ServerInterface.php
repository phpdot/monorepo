<?php

declare(strict_types=1);

/**
 * Transport server interface for SMTP.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Server;

/**
 * Contract for TCP server implementations.
 *
 * The library ships with StreamServer (stream_select based).
 * Implement this for Swoole, Workerman, ReactPHP, Amp, etc.
 */
interface ServerInterface
{
    /**
     * Start listening. Blocks until shutdown.
     */
    public function start(): void;

    /**
     * Stop the server gracefully.
     */
    public function shutdown(): void;
}
