<?php

declare(strict_types=1);

/**
 * Handler Interface
 *
 * Contract for log record handlers.
 * Implementations write formatted log records to a destination.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Log\Handler;

interface HandlerInterface
{
    /**
     * Handle a log record.
     *
     * @param array<string, mixed> $record The log record to handle
     *
     * @return void
     */
    public function handle(array $record): void;

    /**
     * Determine whether this handler processes the given log level.
     *
     * @param int $level The log level to check
     *
     * @return bool True if this handler processes the level
     */
    public function isHandling(int $level): bool;
}
