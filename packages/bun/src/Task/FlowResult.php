<?php

declare(strict_types=1);

/**
 * The outcome of running a {@see Flow}: one {@see StepResult} per step, executed and skipped alike.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Task;

final readonly class FlowResult
{
    /**
     * Holds the per-step results of a finished flow.
     *
     * @param list<StepResult> $steps
     */
    public function __construct(
        public array $steps,
    ) {}

    /**
     * Whether every step in the flow succeeded.
     *
     * @return bool
     */
    public function successful(): bool
    {
        foreach ($this->steps as $step) {
            if (!$step->successful()) {
                return false;
            }
        }

        return true;
    }

    /**
     * The first failed step, or null when all steps passed.
     *
     * @return ?StepResult
     */
    public function firstFailure(): ?StepResult
    {
        foreach ($this->steps as $step) {
            if ($step->executed && $step->exitCode !== 0) {
                return $step;
            }
        }

        return null;
    }

    /**
     * The overall exit code for the flow (0 when every step succeeded).
     *
     * @return int
     */
    public function exitCode(): int
    {
        $failure = $this->firstFailure();

        return $failure === null ? 0 : $failure->exitCode;
    }
}
