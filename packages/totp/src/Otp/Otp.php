<?php

declare(strict_types=1);

/**
 * Shared RFC 4226 core for the HOTP and TOTP generators.
 *
 * Holds the secret, algorithm and digit count, and implements the one operation
 * both variants share: turn a moving factor (an 8-byte counter) into a code via
 * HMAC + dynamic truncation. Subclasses decide what the counter means — a literal
 * counter for {@see Hotp}, a time-step for {@see Totp}.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Totp\Otp;

use PHPdot\Totp\Contract\OtpInterface;
use PHPdot\Totp\Enum\Algorithm;
use PHPdot\Totp\Exception\InvalidParameterException;
use PHPdot\Totp\Secret\Secret;

abstract class Otp implements OtpInterface
{
    /**
     * Holds the secret, hash algorithm and digit count shared by both OTP variants.
     *
     * @param Secret $secret
     * @param Algorithm $algorithm
     * @param int $digits
     */
    public function __construct(
        protected readonly Secret $secret,
        protected readonly Algorithm $algorithm = Algorithm::Sha1,
        protected readonly int $digits = 6,
    ) {
        self::assertDigits($digits);
    }

    public function secret(): Secret
    {
        return $this->secret;
    }

    public function algorithm(): Algorithm
    {
        return $this->algorithm;
    }

    public function digits(): int
    {
        return $this->digits;
    }

    /**
     * Compute the code for a moving factor via HMAC + RFC 4226 dynamic truncation.
     *
     * @param int $counter
     *
     * @return string
     */
    protected function compute(int $counter): string
    {
        $hash = hash_hmac($this->algorithm->value, pack('J', $counter), $this->secret->bytes(), true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0f;

        $binary = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        $modulus = match ($this->digits) {
            6 => 1_000_000,
            7 => 10_000_000,
            8 => 100_000_000,
            default => throw new InvalidParameterException("Unsupported digit count: {$this->digits}."),
        };

        return str_pad((string) ($binary % $modulus), $this->digits, '0', STR_PAD_LEFT);
    }

    /**
     * Constant-time comparison of a known code against user input.
     *
     * @param string $known
     * @param string $user
     *
     * @return bool
     */
    protected function compareCodes(string $known, string $user): bool
    {
        return hash_equals($known, $user);
    }

    /**
     * Asserts the digit count falls within the RFC-supported 6-8 range.
     *
     * @param int $digits
     *
     * @return void
     */
    private static function assertDigits(int $digits): void
    {
        if ($digits < 6 || $digits > 8) {
            throw new InvalidParameterException("Digits must be between 6 and 8, got {$digits}.");
        }
    }
}
