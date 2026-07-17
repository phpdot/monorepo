<?php

declare(strict_types=1);

/**
 * Counter-based one-time passwords (RFC 4226).
 *
 * The moving factor is an explicit counter the caller advances on each use. To
 * tolerate a client that has drifted ahead, `verify()` can look ahead a number
 * of counters; on success it returns the matched counter so you can resynchronise
 * your stored value.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Totp\Otp;

use PHPdot\Totp\Exception\InvalidParameterException;
use PHPdot\Totp\Provisioning\ProvisioningUri;
use PHPdot\Totp\Result\Verification;

final class Hotp extends Otp
{
    /**
     * The code at a specific counter value.
     *
     * @throws InvalidParameterException if `$input` (the counter) is negative
     */
    public function at(int $input): string
    {
        if ($input < 0) {
            throw new InvalidParameterException("Counter must not be negative, got {$input}.");
        }

        return $this->compute($input);
    }

    /**
     * Verify a code at `$counter`, optionally looking ahead `$window` counters.
     *
     * On success the returned {@see Verification} carries the matched counter —
     * store it as your new counter to resynchronise and prevent reuse.
     *
     * @param string $otp
     * @param int $counter
     * @param int $window
     *
     * @throws InvalidParameterException if `$counter` is negative or `$window` is negative
     *
     * @return Verification
     */
    public function verify(string $otp, int $counter, int $window = 0): Verification
    {
        if ($counter < 0) {
            throw new InvalidParameterException("Counter must not be negative, got {$counter}.");
        }

        if ($window < 0) {
            throw new InvalidParameterException("Window must not be negative, got {$window}.");
        }

        for ($i = 0; $i <= $window; ++$i) {
            $step = $counter + $i;

            if ($this->compareCodes($this->compute($step), $otp)) {
                return Verification::pass($step);
            }
        }

        return Verification::fail();
    }

    /**
     * Build the `otpauth://hotp/...` provisioning URI for this generator.
     *
     * @param string $account
     * @param string $issuer
     * @param int $counter
     *
     * @return string
     */
    public function provisioningUri(string $account, string $issuer, int $counter = 0): string
    {
        return (new ProvisioningUri())->hotp($this, $account, $issuer, $counter);
    }
}
