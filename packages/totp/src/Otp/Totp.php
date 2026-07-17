<?php

declare(strict_types=1);

/**
 * Time-based one-time passwords (RFC 6238).
 *
 * The moving factor is the number of whole `period` windows since `epoch`. Codes
 * are read against an injected clock, so time can be frozen in tests and is read
 * fresh on every call under a long-lived Swoole worker.
 *
 * `verify()` scans a `window` of steps either side of now to tolerate drift; the
 * default window of 1 checks exactly the previous, current and next codes. On
 * success it returns the matched time-step — persist it and pass it back as
 * `after` to reject reuse (replay prevention).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Totp\Otp;

use PHPdot\Totp\Clock\SystemClock;
use PHPdot\Totp\Enum\Algorithm;
use PHPdot\Totp\Exception\InvalidParameterException;
use PHPdot\Totp\Provisioning\ProvisioningUri;
use PHPdot\Totp\Result\OtpWindow;
use PHPdot\Totp\Result\Verification;
use PHPdot\Totp\Secret\Secret;
use Psr\Clock\ClockInterface;

final class Totp extends Otp
{
    /**
     * Configures a time-based generator over the given secret, period, clock and epoch.
     *
     * @param Secret $secret
     * @param Algorithm $algorithm
     * @param int $digits
     * @param int $period
     * @param ClockInterface $clock
     * @param int $epoch
     */
    public function __construct(
        Secret $secret,
        Algorithm $algorithm = Algorithm::Sha1,
        int $digits = 6,
        private readonly int $period = 30,
        private readonly ClockInterface $clock = new SystemClock(),
        private readonly int $epoch = 0,
    ) {
        parent::__construct($secret, $algorithm, $digits);

        if ($period < 1) {
            throw new InvalidParameterException("Period must be at least 1 second, got {$period}.");
        }
    }

    /**
     * The length of each time-step in seconds.
     *
     * @return int
     */
    public function period(): int
    {
        return $this->period;
    }

    /**
     * The code valid at a specific Unix timestamp.
     */
    public function at(int $input): string
    {
        return $this->compute($this->counterAt($input));
    }

    /**
     * The code valid right now.
     *
     * @return string
     */
    public function current(): string
    {
        return $this->at($this->timestamp());
    }

    /**
     * The code for the previous period.
     *
     * @return string
     */
    public function previous(): string
    {
        return $this->at($this->timestamp() - $this->period);
    }

    /**
     * The code for the next period.
     *
     * @return string
     */
    public function next(): string
    {
        return $this->at($this->timestamp() + $this->period);
    }

    /**
     * The codes for `$steps` periods either side of now (default ±1: previous,
     * current, next), keyed by time-step. For display, not verification.
     *
     * @param int $steps
     *
     * @throws InvalidParameterException if `$steps` is negative
     *
     * @return OtpWindow
     */
    public function window(int $steps = 1): OtpWindow
    {
        if ($steps < 0) {
            throw new InvalidParameterException("Steps must not be negative, got {$steps}.");
        }

        $center = $this->counterAt($this->timestamp());

        $codes = [];
        for ($i = -$steps; $i <= $steps; ++$i) {
            $codes[$center + $i] = $this->compute($center + $i);
        }

        return new OtpWindow($codes, $center);
    }

    /**
     * Verify `$otp` against the current time, scanning `$window` steps each side.
     *
     * Pass the last successfully used time-step as `$after` to reject any step at
     * or before it — making a code strictly one-time. On success the returned
     * {@see Verification} carries the matched step to persist.
     *
     * @param string $otp
     * @param ?int $after
     * @param int $window
     *
     * @throws InvalidParameterException if `$window` is negative
     *
     * @return Verification
     */
    public function verify(string $otp, int|null $after = null, int $window = 1): Verification
    {
        return $this->verifyAt($otp, $this->timestamp(), $after, $window);
    }

    /**
     * Verify `$otp` against a specific timestamp (otherwise identical to
     * {@see verify()}). Useful for testing and for replaying a known moment.
     *
     * @param int $timestamp
     * @param string $otp
     * @param ?int $after
     * @param int $window
     *
     * @throws InvalidParameterException if `$window` is negative
     *
     * @return Verification
     */
    public function verifyAt(string $otp, int $timestamp, int|null $after = null, int $window = 1): Verification
    {
        if ($window < 0) {
            throw new InvalidParameterException("Window must not be negative, got {$window}.");
        }

        $center = $this->counterAt($timestamp);

        for ($i = -$window; $i <= $window; ++$i) {
            $step = $center + $i;

            if ($after !== null && $step <= $after) {
                continue;
            }

            if ($this->compareCodes($this->compute($step), $otp)) {
                return Verification::pass($step);
            }
        }

        return Verification::fail();
    }

    /**
     * Build the `otpauth://totp/...` provisioning URI for this generator.
     *
     * @param string $account
     * @param string $issuer
     *
     * @return string
     */
    public function provisioningUri(string $account, string $issuer): string
    {
        return (new ProvisioningUri())->totp($this, $account, $issuer);
    }

    /**
     * The current Unix timestamp read from the injected clock.
     *
     * @return int
     */
    private function timestamp(): int
    {
        return $this->clock->now()->getTimestamp();
    }

    /**
     * The time-step index for a Unix timestamp (whole periods elapsed since the epoch).
     *
     * @param int $timestamp
     *
     * @return int
     */
    private function counterAt(int $timestamp): int
    {
        return intdiv($timestamp - $this->epoch, max(1, $this->period));
    }
}
