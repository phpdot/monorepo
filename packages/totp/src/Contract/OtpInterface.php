<?php

declare(strict_types=1);

/**
 * Shared surface of the counter-based (HOTP) and time-based (TOTP) generators.
 *
 * `at()` takes the moving factor each variant defines — a counter for HOTP, a
 * Unix timestamp for TOTP — and returns the corresponding code.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Totp\Contract;

use PHPdot\Totp\Enum\Algorithm;
use PHPdot\Totp\Secret\Secret;

interface OtpInterface
{
    /**
     * The shared secret this generator derives its codes from.
     *
     * @return Secret
     */
    public function secret(): Secret;

    /**
     * The HMAC hash algorithm used to derive codes.
     *
     * @return Algorithm
     */
    public function algorithm(): Algorithm;

    /**
     * The number of digits in each generated code.
     *
     * @return int
     */
    public function digits(): int;

    /**
     * The code for the given moving factor (HOTP counter or TOTP timestamp).
     *
     * @param int $input
     *
     * @return string
     */
    public function at(int $input): string;
}
