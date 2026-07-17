<?php

declare(strict_types=1);

/**
 * A named, reusable unit of build work. The handler receives the Bun service and returns an exit
 * code (0 = success). Sequence tasks with {@see then()}, which returns a {@see Flow}.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Task;

use Closure;
use PHPdot\Bun\Bun;

final class Task
{
    /**
     * @var Closure(Bun): int
     */
    private readonly Closure $handler;

    /**
     * Names the task and captures its handler closure (which returns an exit code).
     *
     * @param callable(Bun): int $handler returns an exit code (0 = success)
     * @param string $name
     */
    public function __construct(
        public readonly string $name,
        callable $handler,
    ) {
        $this->handler = $handler(...);
    }

    /**
     * Sequence this task before $next, producing a Flow. Type-safe: takes a Task, not a closure.
     *
     * @param Task $next
     *
     * @return Flow
     */
    public function then(Task $next): Flow
    {
        return new Flow([$this, $next]);
    }

    /**
     * Invokes the task's handler against the Bun service and returns its exit code.
     *
     * @internal Run by {@see Flow}.
     *
     * @param Bun $bun
     *
     * @return int
     */
    public function execute(Bun $bun): int
    {
        return ($this->handler)($bun);
    }
}
