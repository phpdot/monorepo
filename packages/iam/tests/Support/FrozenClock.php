<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Support;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * A clock frozen at a fixed Unix timestamp that tests can advance, for
 * deterministic expiry coverage without sleeping.
 */
final class FrozenClock implements ClockInterface
{
    private int $timestamp;

    public function __construct(int $timestamp)
    {
        $this->timestamp = $timestamp;
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('@' . $this->timestamp);
    }

    public function advance(int $seconds): void
    {
        $this->timestamp += $seconds;
    }
}
