<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Integration;

use PHPdot\Bun\Bun;
use PHPdot\Bun\Http\HttpClient;
use PHPdot\Bun\Task\StepResult;
use PHPdot\Bun\Task\Tasks;
use PHPdot\Bun\Tests\Support\IntegrationBun;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Slice 2 headline acceptance, driven through a real Tasks/Flow pipeline (not direct calls): with a
 * real Bun binary, `install` creates package.json + lockfile and `x` runs the installed CLI tool.
 * workingDir keeps the toolchain inside the project dir.
 */
#[Group('integration')]
final class InstallAndRunTest extends TestCase
{
    private string $project;

    protected function setUp(): void
    {
        if (getenv('BUN_LIVE') !== '1') {
            self::markTestSkipped('Live Bun integration test — set BUN_LIVE=1 to run it (downloads the real Bun binary over the network).');
        }
        if (!class_exists(HttpClient::class)) {
            self::markTestSkipped('symfony/http-client is required for the integration test');
        }
        $this->project = sys_get_temp_dir() . '/phpdot-bun-project-' . uniqid();
        mkdir($this->project, 0755, true);
    }

    protected function tearDown(): void
    {
        if (isset($this->project)) {
            $this->deleteTree($this->project);
        }
    }

    public function testInstallThenRunToolViaFlow(): void
    {
        $bun = IntegrationBun::create(workingDir: $this->project);
        $tasks = new Tasks($bun);

        // A real two-step pipeline through the Task/Flow API — install, then run the installed tool.
        $install = $tasks->task('install', static fn(Bun $b): int => $b->install(['cowsay']));
        $run = $tasks->task('run', static fn(Bun $b): int => $b->x('cowsay', ['Moo from phpdot/bun']));

        $result = $install->then($run)->run($bun);

        self::assertTrue($result->successful(), 'install → x flow should succeed (exit ' . $result->exitCode() . ')');
        self::assertSame(['install', 'run'], array_map(static fn(StepResult $s): string => $s->task, $result->steps));

        self::assertFileExists($this->project . '/package.json');
        self::assertTrue(
            is_file($this->project . '/bun.lock') || is_file($this->project . '/bun.lockb'),
            'a bun lockfile should be created',
        );
        self::assertDirectoryExists($this->project . '/node_modules/cowsay');
    }

    private function deleteTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        /** @var list<string> $entries */
        $entries = array_diff((array) scandir($path), ['.', '..']);
        foreach ($entries as $entry) {
            $full = $path . DIRECTORY_SEPARATOR . $entry;
            is_dir($full) && !is_link($full) ? $this->deleteTree($full) : @unlink($full);
        }
        @rmdir($path);
    }
}
