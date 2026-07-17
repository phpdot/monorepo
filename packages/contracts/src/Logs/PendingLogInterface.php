<?php

declare(strict_types=1);

/**
 * Pending Log handle.
 *
 * The deferred result of a tracer or span log call: the record carries the
 * call-site trace correlation and is written when the handle is released at
 * the end of the statement. `secure()` flags it for encrypted export.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Contracts\Logs;

interface PendingLogInterface
{
    /**
     * Mark this log line sensitive: the backend encrypts its message and context
     * together (fail-closed — dropped, never plaintext, if it cannot be protected).
     * Call it on the same statement as the log call, before the handle is released:
     * $tracer->error('...')->secure().
     *
     * @return static
     */
    public function secure(): static;
}
