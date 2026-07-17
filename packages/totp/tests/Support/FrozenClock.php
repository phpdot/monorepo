<?php

declare(strict_types=1);

namespace PHPdot\Totp\Tests\Support;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * A clock frozen at a fixed Unix timestamp, for deterministic TOTP tests.
 */
final class FrozenClock implements ClockInterface
{
    public function __construct(
        private readonly int $timestamp,
    ) {}

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('@' . $this->timestamp);
    }
}
