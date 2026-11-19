<?php

declare(strict_types=1);

/**
 * Default PSR-20 clock returning the real current time.
 *
 * Time is injected as a `Psr\Clock\ClockInterface` so it can be frozen in tests
 * and so nothing hard-codes `time()` — which matters under Swoole, where a
 * long-lived worker must read the wall clock fresh on every call.
 *
 * `#[Binds]` makes it the default `ClockInterface` where phpdot/package reads the
 * attribute; the container's own directory scan does not. `OtpFactory` and `Totp`
 * default to it either way, so they resolve with no extra wiring; pass your own
 * clock to override.
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
