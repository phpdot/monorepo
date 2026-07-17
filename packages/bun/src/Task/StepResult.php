<?php

declare(strict_types=1);

/**
 * The outcome of a single step in a {@see Flow}. The private constructor plus named constructors
 * make an invalid state (e.g. "skipped with exit 0") unconstructable.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Task;

final readonly class StepResult
{
    /**
     * Hold one step outcome (task, whether it executed, exit code) — use the named constructors.
     *
     * @param string $task
     * @param bool $executed
     * @param int $exitCode
     */
    private function __construct(
        public string $task,
        public bool $executed,
        public int $exitCode,
    ) {}

    /**
     * Build a result for a step that ran, capturing its exit code.
     *
     * @param string $task
     * @param int $code
     *
     * @return self
     */
    public static function executed(string $task, int $code): self
    {
        return new self($task, true, $code);
    }

    /**
     * Build a result for a step that was skipped.
     *
     * @param string $task
     *
     * @return self
     */
    public static function skipped(string $task): self
    {
        return new self($task, false, -1);
    }

    /**
     * Whether the step ran and exited zero.
     *
     * @return bool
     */
    public function successful(): bool
    {
        return $this->executed && $this->exitCode === 0;
    }
}
