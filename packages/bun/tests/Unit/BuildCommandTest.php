<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Unit;

use PHPdot\Bun\Command\BuildCommand;
use PHPdot\Bun\Tests\Support\TestBun;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Verifies the bun:build console command maps every CLI option onto the build invocation —
 * against the fake runner, so the mapping is asserted argv-exactly without a real binary.
 */
final class BuildCommandTest extends TestCase
{
    private TestBun $fake;
    private string $cwd;
    private string $workdir;

    protected function setUp(): void
    {
        $this->fake = new TestBun();
        $this->cwd = (string) getcwd();
        $this->workdir = sys_get_temp_dir() . '/phpdot-bun-cmdtest-' . uniqid();
        mkdir($this->workdir, 0755, true);
        chdir($this->workdir);
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);
        $this->fake->cleanup();
        @rmdir($this->workdir);
    }

    public function testEveryNewOptionReachesTheBuildArguments(): void
    {
        $tester = new CommandTester(new BuildCommand($this->fake->bun));

        $exit = $tester->execute([
            'entry' => ['app.ts'],
            '--root' => 'resources/js',
            '--public-path' => '/build/',
            '--entry-naming' => '[name].[ext]',
            '--css-chunking' => true,
            '--keep-names' => true,
            '--reject-unresolved' => true,
            '--packages' => 'external',
            '--loader' => ['.svg:text', '.png:dataurl'],
            '--conditions' => ['phpdot'],
            '--env' => 'PUBLIC_*',
            '--metafile-md' => 'graph.md',
            '--no-clear-screen' => true,
        ]);

        self::assertSame(0, $exit);

        $args = $this->fake->lastArgs();

        self::assertContains('--root=resources/js', $args);
        self::assertContains('--public-path=/build/', $args);
        self::assertContains('--entry-naming=[name].[ext]', $args);
        self::assertContains('--css-chunking', $args);
        self::assertContains('--keep-names', $args);
        self::assertContains('--reject-unresolved', $args);
        self::assertContains('--packages=external', $args);
        self::assertContains('--loader=.svg:text', $args);
        self::assertContains('--loader=.png:dataurl', $args);
        self::assertContains('--conditions=phpdot', $args);
        self::assertContains('--env=PUBLIC_*', $args);
        self::assertContains('--metafile-md=graph.md', $args);
        self::assertContains('--no-clear-screen', $args);
    }

    public function testAnInvalidPackagesModeIsDroppedNotForwarded(): void
    {
        $tester = new CommandTester(new BuildCommand($this->fake->bun));

        self::assertSame(0, $tester->execute(['entry' => ['app.ts'], '--packages' => 'sideways']));

        foreach ($this->fake->lastArgs() as $arg) {
            self::assertStringStartsNotWith('--packages=', $arg);
        }
    }

    public function testOmittedOptionsEmitNoneOfTheNewFlags(): void
    {
        $tester = new CommandTester(new BuildCommand($this->fake->bun));

        self::assertSame(0, $tester->execute(['entry' => ['app.ts']]));

        $forbidden = ['--root=', '--public-path=', '--entry-naming=', '--css-chunking', '--keep-names',
            '--reject-unresolved', '--packages=', '--loader=', '--conditions=', '--env=', '--metafile-md=', '--no-clear-screen'];

        foreach ($this->fake->lastArgs() as $arg) {
            foreach ($forbidden as $prefix) {
                self::assertFalse(str_starts_with($arg, $prefix), "unexpected {$arg}");
            }
        }
    }
}
