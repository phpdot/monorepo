<?php

declare(strict_types=1);

/**
 * Entry point for building OTP generators — inject this into your services.
 *
 * Holds the injected clock and stamps it onto every {@see Totp} it creates, so a
 * frozen test clock or a Swoole-friendly clock flows through automatically.
 * Stateless and safe to share across coroutines — registered as a singleton.
 *
 *     public function __construct(private OtpFactory $otp) {}
 *
 *     $secret = $this->otp->generateSecret();
 *     $totp   = $this->otp->totp($secret);
 *     $code   = $totp->current();
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Totp;

use PHPdot\Container\Attribute\Singleton;
use PHPdot\Totp\Clock\SystemClock;
use PHPdot\Totp\Enum\Algorithm;
use PHPdot\Totp\Otp\Hotp;
use PHPdot\Totp\Otp\Totp;
use PHPdot\Totp\Secret\Secret;
use Psr\Clock\ClockInterface;

#[Singleton]
final readonly class OtpFactory
{
    /**
     * Holds the clock stamped onto every time-based generator this factory builds.
     *
     * @param ClockInterface $clock
     */
    public function __construct(
        private ClockInterface $clock = new SystemClock(),
    ) {}

    /**
     * Build a time-based generator (RFC 6238) bound to the injected clock.
     *
     * @param Secret $secret
     * @param Algorithm $algorithm
     * @param int $digits
     * @param int $period
     * @param int $epoch
     *
     * @return Totp
     */
    public function totp(
        Secret $secret,
        Algorithm $algorithm = Algorithm::Sha1,
        int $digits = 6,
        int $period = 30,
        int $epoch = 0,
    ): Totp {
        return new Totp($secret, $algorithm, $digits, $period, $this->clock, $epoch);
    }

    /**
     * Build a counter-based generator (RFC 4226).
     *
     * @param Secret $secret
     * @param Algorithm $algorithm
     * @param int $digits
     *
     * @return Hotp
     */
    public function hotp(
        Secret $secret,
        Algorithm $algorithm = Algorithm::Sha1,
        int $digits = 6,
    ): Hotp {
        return new Hotp($secret, $algorithm, $digits);
    }

    /**
     * Generate a fresh random secret (CSPRNG, 128-bit minimum).
     *
     * @param int $length
     *
     * @return Secret
     */
    public function generateSecret(int $length = 20): Secret
    {
        return Secret::generate($length);
    }
}
