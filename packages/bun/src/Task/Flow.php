<?php

declare(strict_types=1);

/**
 * An ordered, immutable sequence of tasks. Running it is fail-fast: once a step fails, the rest are
 * reported as skipped. Linear by construction, so cycles are impossible.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Task;

use PHPdot\Bun\Bun;

final class Flow
{
    /**
     * Holds the ordered tasks that make up the flow.
     *
     * @param list<Task> $steps
     */
    public function __construct(
        private readonly array $steps,
    ) {}

    /**
     * Append a task, returning a new Flow (the original is unchanged — coroutine-safe).
     *
     * @param Task $next
     *
     * @return Flow
     */
    public function then(Task $next): Flow
    {
        return new Flow([...$this->steps, $next]);
    }

    /**
     * Run each task in order, short-circuiting on the first failure, and return the result.
     *
     * @param Bun $bun
     *
     * @return FlowResult
     */
    public function run(Bun $bun): FlowResult
    {
        $results = [];
        $failed = false;

        foreach ($this->steps as $step) {
            if ($failed) {
                $results[] = StepResult::skipped($step->name);

                continue;
            }

            $code = $step->execute($bun);
            $results[] = StepResult::executed($step->name, $code);

            if ($code !== 0) {
                $failed = true;
            }
        }

        return new FlowResult($results);
    }

    /**
     * Returns the flow's task names in execution order.
     *
     * @return list<string>
     */
    public function stepNames(): array
    {
        return array_map(static fn(Task $t): string => $t->name, $this->steps);
    }
}
