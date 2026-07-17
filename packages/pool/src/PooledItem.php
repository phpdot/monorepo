<?php

declare(strict_types=1);

/**
 * Internal wrapper pairing a connection with its last-released timestamp.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Pool;

final class PooledItem
{
    /**
     * Pair a pooled connection with its last-released timestamp.
     *
     * @param object $connection
     * @param float $lastReleasedAt
     */
    public function __construct(
        public readonly object $connection,
        public float $lastReleasedAt,
    ) {}
}
