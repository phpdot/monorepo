<?php

declare(strict_types=1);

/**
 * Writer contract — the backend export boundary.
 *
 * A pure export point: it decides what to do with an already-scoped,
 * already-correlated record — write it, encrypt it, or discard it.
 * Implementations are stateless singletons.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Contracts\Logs;

interface WriterInterface
{
    /**
     * Export a single record — a log line or a finished span snapshot.
     *
     * A record marked sensitive that cannot be protected MUST be dropped or
     * redacted, never written in plaintext (fail-closed).
     *
     * @param array<string, mixed> $record The record to export.
     *
     * @return void
     */
    public function write(array $record): void;
}
