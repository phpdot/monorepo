<?php

declare(strict_types=1);

/**
 * Registry of named, reusable tasks plus a runner. Define tasks with {@see task()}, sequence them
 * with {@see Task::then()}, and run a single named task with {@see run()}.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Task;

use PHPdot\Bun\Bun;

final class Tasks
{
    /**
     * @var array<string, Task>
     */
    private array $tasks = [];

    /**
     * Bind the task registry to the Bun service its tasks drive.
     *
     * @param Bun $bun
     */
    public function __construct(
        private readonly Bun $bun,
    ) {}

    /**
     * Define a reusable named task; returns the Task to reference or sequence.
     *
     * @param callable(Bun): int $handler
     * @param string $name
     *
     * @return Task
     */
    public function task(string $name, callable $handler): Task
    {
        return $this->tasks[$name] = new Task($name, $handler);
    }

    /**
     * Returns the task registered under the name, or throws when none is defined.
     *
     * @param string $name
     *
     * @throws UnknownTaskException
     *
     * @return Task
     */
    public function get(string $name): Task
    {
        return $this->tasks[$name] ?? throw new UnknownTaskException($name, array_keys($this->tasks));
    }

    /**
     * Run a single task by name (defaults to the first defined) as a one-step flow.
     *
     * @param ?string $name
     *
     * @throws UnknownTaskException
     *
     * @return FlowResult
     */
    public function run(?string $name = null): FlowResult
    {
        $name ??= array_key_first($this->tasks);
        if ($name === null) {
            throw new UnknownTaskException('(none)', []);
        }

        return (new Flow([$this->get($name)]))->run($this->bun);
    }
}
