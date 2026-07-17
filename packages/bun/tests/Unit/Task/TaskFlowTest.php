<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Unit\Task;

use PHPdot\Bun\Bun;
use PHPdot\Bun\Task\StepResult;
use PHPdot\Bun\Task\Tasks;
use PHPdot\Bun\Task\UnknownTaskException;
use PHPdot\Bun\Tests\Support\TestBun;
use PHPUnit\Framework\TestCase;

final class TaskFlowTest extends TestCase
{
    private TestBun $fake;

    protected function setUp(): void
    {
        $this->fake = new TestBun();
    }

    protected function tearDown(): void
    {
        $this->fake->cleanup();
    }

    public function testFlowRunsStepsInOrderAndSucceeds(): void
    {
        $log = [];
        $tasks = new Tasks($this->fake->bun);
        $a = $tasks->task('a', function (Bun $b) use (&$log): int {
            $log[] = 'a';

            return 0;
        });
        $c = $tasks->task('c', function (Bun $b) use (&$log): int {
            $log[] = 'c';

            return 0;
        });

        $result = $a->then($c)->run($this->fake->bun);

        self::assertTrue($result->successful());
        self::assertSame(['a', 'c'], $log);
        self::assertSame(0, $result->exitCode());
        self::assertCount(2, $result->steps);
    }

    public function testFailFastSkipsRemainingSteps(): void
    {
        $log = [];
        $tasks = new Tasks($this->fake->bun);
        $ok = $tasks->task('ok', function () use (&$log): int {
            $log[] = 'ok';

            return 0;
        });
        $bad = $tasks->task('bad', function () use (&$log): int {
            $log[] = 'bad';

            return 3;
        });
        $never = $tasks->task('never', function () use (&$log): int {
            $log[] = 'never';

            return 0;
        });

        $result = $ok->then($bad)->then($never)->run($this->fake->bun);

        self::assertFalse($result->successful());
        self::assertSame(['ok', 'bad'], $log, 'the step after the failure must not run');
        self::assertSame(3, $result->exitCode());
        self::assertSame('bad', $result->firstFailure()?->task);

        self::assertTrue($result->steps[0]->successful());
        self::assertFalse($result->steps[1]->successful());
        self::assertFalse($result->steps[2]->executed);
        self::assertSame(-1, $result->steps[2]->exitCode);
    }

    public function testFlowThenIsImmutable(): void
    {
        $tasks = new Tasks($this->fake->bun);
        $flow1 = $tasks->task('a', fn(): int => 0)->then($tasks->task('b', fn(): int => 0));
        $flow2 = $flow1->then($tasks->task('c', fn(): int => 0));

        self::assertSame(['a', 'b'], $flow1->stepNames());
        self::assertSame(['a', 'b', 'c'], $flow2->stepNames());
    }

    public function testTasksRunByName(): void
    {
        $tasks = new Tasks($this->fake->bun);
        $tasks->task('build', fn(): int => 0);

        self::assertTrue($tasks->run('build')->successful());
    }

    public function testUnknownTaskThrows(): void
    {
        $tasks = new Tasks($this->fake->bun);

        $this->expectException(UnknownTaskException::class);
        $tasks->get('nope');
    }

    public function testStepResultStates(): void
    {
        self::assertTrue(StepResult::executed('y', 0)->successful());
        self::assertFalse(StepResult::executed('z', 1)->successful());
        self::assertFalse(StepResult::skipped('x')->successful());
    }
}
