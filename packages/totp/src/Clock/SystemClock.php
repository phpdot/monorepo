<?php

declare(strict_types=1);

/**
 * Default PSR-20 clock returning the real current time.
 *
 * Time is injected as a `Psr\Clock\ClockInterface` so it can be frozen in tests
 * and so nothing hard-codes `time()` — which matters under Swoole, where a
 * long-lived worker must read the wall clock fresh on every call.
 *
 * Bound as the default `ClockInterface` via `#[Binds]`, so `OtpFactory` resolves
 * out of the box in a phpdot/container app with no extra wiring; override the
 * binding to supply your own clock.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Totp\Clock;

use DateTimeImmutable;
use PHPdot\Container\Attribute\Binds;
use PHPdot\Container\Attribute\Singleton;
use Psr\Clock\ClockInterface;

#[Singleton]
#[Binds(ClockInterface::class)]
final class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now');
    }
}
