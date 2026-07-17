<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Support;

use PHPdot\Bun\Process\ProcessResult;
use PHPdot\Bun\Process\ProcessRunnerInterface;

/**
 * Test double for {@see ProcessRunnerInterface}. Returns queued results in order; once the queue
 * is exhausted it returns a configurable default. Records every invocation.
 */
final class FakeProcessRunner implements ProcessRunnerInterface
{
    /** @var list<ProcessResult> */
    private array $queue;

    /** @var list<array{executable: string, args: list<string>, cwd: ?string}> */
    public array $calls = [];

    /** @var list<array{executable: string, args: list<string>, cwd: ?string}> */
    public array $passthroughCalls = [];

    /**
     * @param list<ProcessResult> $queue
     */
    public function __construct(
        array $queue = [],
        private readonly ?ProcessResult $default = null,
        private readonly int $passthroughExit = 0,
    ) {
        $this->queue = $queue;
    }

    public function run(string $executable, array $args = [], ?string $cwd = null): ProcessResult
    {
        $this->calls[] = ['executable' => $executable, 'args' => $args, 'cwd' => $cwd];

        $next = array_shift($this->queue);
        if ($next !== null) {
            return $next;
        }

        return $this->default ?? new ProcessResult(0, '', '');
    }

    public function passthrough(string $executable, array $args = [], ?string $cwd = null): int
    {
        $this->passthroughCalls[] = ['executable' => $executable, 'args' => $args, 'cwd' => $cwd];

        return $this->passthroughExit;
    }
}
