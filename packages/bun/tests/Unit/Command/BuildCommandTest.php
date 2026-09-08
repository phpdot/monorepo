<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Unit\Command;

use PHPdot\Bun\Command\BuildCommand;
use PHPdot\Bun\Tests\Support\TestBun;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class BuildCommandTest extends TestCase
{
    private TestBun $fake;
    private string $cwd;
    private string $workdir;

    protected function setUp(): void
    {
        $this->fake = new TestBun();
        // The throwaway metafile lives under home/build; run in a throwaway cwd so nothing touches the repo.
        $this->cwd = (string) getcwd();
        $this->workdir = sys_get_temp_dir() . '/phpdot-bun-cmdtest-' . uniqid();
        mkdir($this->workdir, 0o755, true);
        chdir($this->workdir);
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);
        $this->fake->cleanup();
        @rmdir($this->workdir);
    }

    public function testMapsOptionsToBunBuildFlags(): void
    {
        $tester = new CommandTester(new BuildCommand($this->fake->bun));
        $tester->execute([
            'entry' => ['src/index.ts'],
            '--out-dir' => 'dist',
            '--target' => 'browser',
            '--minify' => true,
            '--splitting' => true,
            '--hashed-names' => true,
            '--define' => ['A=1', 'B=2'],
            '--external' => ['react'],
        ]);

        $tester->assertCommandIsSuccessful();
        self::assertSame([
            'build',
            'src/index.ts',
            '--outdir=dist',
            '--target=browser',
            '--minify',
            '--splitting',
            '--entry-naming=[dir]/[name]-[hash].[ext]',
            '--chunk-naming=[name]-[hash].[ext]',
            '--define=A=1',
            '--define=B=2',
            '--external=react',
            '--metafile=' . $this->fake->config->homeDir . '/build/metafile.json',
        ], $this->fake->lastArgs());
    }

    public function testBuildsMultipleEntrypoints(): void
    {
        $tester = new CommandTester(new BuildCommand($this->fake->bun));
        $tester->execute(['entry' => ['a.ts', 'b.ts'], '--out-dir' => 'out']);

        $tester->assertCommandIsSuccessful();
        self::assertSame(
            ['build', 'a.ts', 'b.ts', '--outdir=out', '--metafile=' . $this->fake->config->homeDir . '/build/metafile.json'],
            $this->fake->lastArgs(),
        );
    }
}
