<?php

declare(strict_types=1);

/**
 * Null Handler
 *
 * A no-op handler that discards all log records.
 * Useful for testing or disabling logging in specific channels.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Log\Handler;

final class NullHandler implements HandlerInterface
{
    /**
     * Handle a log record by discarding it.
     *
     * @param array<string, mixed> $record The log record to discard
     *
     * @return void
     */
    public function handle(array $record): void {}

    /**
     * Determine whether this handler processes the given log level.
     *
     * @param int $level The log level to check
     *
     * @return bool Always true — this handler accepts all levels and discards them
     */
    public function isHandling(int $level): bool
    {
        return true;
    }
}
