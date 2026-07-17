<?php

declare(strict_types=1);

/**
 * A contiguous run of TOTP codes centred on a moment in time.
 *
 * Returned by {@see Totp::window()} for display — e.g. showing the previous,
 * current and next codes during enrollment, or rendering a drift table. Keyed by
 * time-step so callers can correlate a code with its exact step.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Totp\Result;

use PHPdot\Totp\Exception\OtpException;

final readonly class OtpWindow
{
    /**
     * Holds the window's codes keyed by time-step, with the centre step recorded.
     *
     * @param array<int, string> $codes Map of time-step => code, in ascending step order.
     * @param int $currentTimestep
     */
    public function __construct(
        public array $codes,
        public int $currentTimestep,
    ) {}

    /**
     * The code at the centre time-step.
     *
     * @return string
     */
    public function current(): string
    {
        return $this->codes[$this->currentTimestep]
            ?? throw new OtpException('Window does not contain its current time-step.');
    }

    /**
     * The code one step earlier, or null if the window does not reach it.
     *
     * @return ?string
     */
    public function previous(): string|null
    {
        return $this->codes[$this->currentTimestep - 1] ?? null;
    }

    /**
     * The code one step later, or null if the window does not reach it.
     *
     * @return ?string
     */
    public function next(): string|null
    {
        return $this->codes[$this->currentTimestep + 1] ?? null;
    }

    /**
     * Every code in the window, in ascending time-step order.
     *
     * @return list<string>
     */
    public function all(): array
    {
        return array_values($this->codes);
    }
}
