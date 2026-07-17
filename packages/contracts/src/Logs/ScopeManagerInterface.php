<?php

declare(strict_types=1);

/**
 * Scope manager contract — the per-coroutine current-span store.
 *
 * The single authoritative source of the "current span" for every backend: a
 * per-coroutine stack of active spans. Implementations are bound to one
 * coroutine and reset at its end. This is the seam that lets a stateless
 * singleton Tracer read a per-coroutine current span natively.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Contracts\Logs;

interface ScopeManagerInterface
{
    /**
     * The top of the per-coroutine stack, or null if empty.
     *
     * Deliberately nullable, unlike TracerInterface::current(), which wraps the
     * empty case as a no-op span.
     *
     * @return ?SpanInterface
     */
    public function current(): ?SpanInterface;

    /**
     * Push any span as the new current span on the per-coroutine stack.
     *
     * @param SpanInterface $span
     *
     * @return void
     */
    public function activate(SpanInterface $span): void;

    /**
     * Identity-remove the given span from the stack. The root frame is protected
     * and never removed; an absent span is a no-op. Idempotent, and never exports.
     *
     * @param SpanInterface $span
     *
     * @return void
     */
    public function deactivate(SpanInterface $span): void;

    /**
     * Kernel-owned request-boundary drain: end and export every remaining frame on the
     * per-coroutine stack LIFO, including the root, then clear the per-coroutine slot.
     * Runs in the kernel's request finally to flush leaked frames before the coroutine
     * is reused. Idempotent. Export lives in SpanInterface::end(); this manager holds no
     * writer reference of its own.
     *
     * @return void
     */
    public function close(): void;
}
