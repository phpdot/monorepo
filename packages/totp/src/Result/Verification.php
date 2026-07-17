<?php

declare(strict_types=1);

/**
 * The result of verifying a submitted code.
 *
 * On success, `timestep` carries the matched moving factor (TOTP time-step or
 * HOTP counter). Persist it and pass it back as the `after` argument on the next
 * verification to make a code strictly one-time — this is the package's
 * replay-prevention primitive. Storing it is the consumer's responsibility.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Totp\Result;

final readonly class Verification
{
    /**
     * Records whether verification passed and, on success, the matched moving factor.
     *
     * @param bool $passed
     * @param int|null $timestep
     */
    private function __construct(
        public bool $passed,
        public int|null $timestep = null,
    ) {}

    /**
     * A passing result carrying the matched moving factor (time-step or counter).
     *
     * @param int $timestep
     *
     * @return self
     */
    public static function pass(int $timestep): self
    {
        return new self(true, $timestep);
    }

    /**
     * A failing result, with no matched moving factor.
     *
     * @return self
     */
    public static function fail(): self
    {
        return new self(false);
    }
}
