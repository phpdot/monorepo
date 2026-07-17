<?php

declare(strict_types=1);

/**
 * Span context contract — the W3C trace identity that travels with every span.
 *
 * Exposes the immutable identity bits — trace id, span id, parent span id, and
 * the W3C sampled flag — plus the single canonical `traceparent` codec used for
 * outbound propagation. Implementations are immutable value objects; the
 * identity is decided once at construction and never re-derived by consumers.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Contracts\Logs;

interface SpanContextInterface
{
    /**
     * The W3C trace id (32 lowercase hex characters), shared by every span in the trace.
     *
     * @return string
     */
    public function traceId(): string;

    /**
     * The W3C span id (16 lowercase hex characters) of this span.
     *
     * @return string
     */
    public function spanId(): string;

    /**
     * The span id this span descends from, or null at the root of the trace.
     *
     * @return ?string
     */
    public function parentSpanId(): ?string;

    /**
     * The W3C sampled flag (bit 0 of trace-flags): whether this trace is recorded.
     *
     * @return bool
     */
    public function sampled(): bool;

    /**
     * Emit the W3C `traceparent` header value (version `00`, lowercase) for outbound propagation.
     *
     * @return string
     */
    public function toTraceparent(): string;
}
