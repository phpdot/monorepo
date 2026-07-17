<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Unit;

use PHPdot\Bun\Build\BuildOptions;
use PHPdot\Bun\Build\BuildSpec;
use PHPdot\Bun\Tests\Support\TestBun;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the Bun service maps each method to the correct bun argv.
 */
final class BunTest extends TestCase
{
    private TestBun $fake;
    private string $cwd;
    private string $workdir;

    protected function setUp(): void
    {
        $this->fake = new TestBun();
        // Builds resolve the throwaway metafile relative to the cwd; isolate it so the suite stays
        // hermetic regardless of the repo's working tree.
        $this->cwd = (string) getcwd();
        $this->workdir = sys_get_temp_dir() . '/phpdot-bun-buntest-' . uniqid();
        mkdir($this->workdir, 0755, true);
        chdir($this->workdir);
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);
        $this->fake->cleanup();
        self::deleteTree($this->workdir);
    }

    private static function deleteTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        /** @var list<string> $entries */
        $entries = array_diff((array) scandir($path), ['.', '..']);
        foreach ($entries as $entry) {
            $full = $path . DIRECTORY_SEPARATOR . $entry;
            is_dir($full) && !is_link($full) ? self::deleteTree($full) : @unlink($full);
        }
        @rmdir($path);
    }

    public function testInstallMapsToBunAdd(): void
    {
        self::assertSame(0, $this->fake->bun->install(['lodash', 'axios']));
        self::assertSame(['add', 'lodash', 'axios'], $this->fake->lastArgs());
        self::assertSame($this->fake->binaryPath, $this->fake->lastExecutable());
    }

    public function testInstallDevMapsToBunAddDev(): void
    {
        $this->fake->bun->install(['typescript'], dev: true);
        self::assertSame(['add', '--dev', 'typescript'], $this->fake->lastArgs());
    }

    public function testRemoveMapsToBunRemove(): void
    {
        $this->fake->bun->remove(['lodash']);
        self::assertSame(['remove', 'lodash'], $this->fake->lastArgs());
    }

    public function testViewMapsToBunPmView(): void
    {
        $this->fake->bun->view('react');
        self::assertSame(['pm', 'view', 'react'], $this->fake->lastArgs());
    }

    public function testRunMapsToBunRunWithArgs(): void
    {
        $this->fake->bun->run('dev', ['--port', '3000']);
        self::assertSame(['run', 'dev', '--port', '3000'], $this->fake->lastArgs());
    }

    public function testXMapsToBunX(): void
    {
        $this->fake->bun->x('prettier', ['--write', '.']);
        self::assertSame(['x', 'prettier', '--write', '.'], $this->fake->lastArgs());
    }

    public function testBuildAppliesProductionDefaults(): void
    {
        $this->fake->bun->build('app.ts');

        self::assertSame([
            'build', 'app.ts',
            '--outdir=public/build',
            '--target=browser',
            '--minify',
            '--splitting',
            '--entry-naming=[dir]/[name]-[hash].[ext]',
            '--chunk-naming=[name]-[hash].[ext]',
            '--metafile=.phpdot/build/metafile.json',
        ], $this->fake->lastArgs());
    }

    public function testBuildClosureAdjustsDefaults(): void
    {
        $this->fake->bun->build(['a.ts', 'b.ts'], fn(BuildSpec $b): BuildSpec => $b->noMinify()->outDir('dist'));

        $args = $this->fake->lastArgs();
        self::assertSame(['build', 'a.ts', 'b.ts'], array_slice($args, 0, 3));
        self::assertContains('--outdir=dist', $args);
        self::assertNotContains('--minify', $args);
        self::assertNotContains('--outdir=public/build', $args);
    }

    public function testWatchUsesDevPreset(): void
    {
        $this->fake->bun->watch('app.ts');

        self::assertSame([
            'build', 'app.ts',
            '--outdir=public/build',
            '--target=browser',
            '--splitting',
            '--sourcemap=linked',
            '--watch',
        ], $this->fake->lastArgs());
    }

    public function testBuildWithUsesExplicitOptions(): void
    {
        $this->fake->bun->buildWith(['app.ts'], new BuildOptions(minify: true, splitting: true, hashedNames: true));

        self::assertSame([
            'build', 'app.ts',
            '--minify',
            '--splitting',
            '--entry-naming=[dir]/[name]-[hash].[ext]',
            '--chunk-naming=[name]-[hash].[ext]',
        ], $this->fake->lastArgs());
    }

    public function testBuildFailsLoudlyWhenBunLeavesACorruptMetafile(): void
    {
        // Simulate bun exiting 0 but leaving a corrupt metafile (killed mid-write, disk full): the
        // distillation must fail the build rather than report a hollow success with no usable manifest.
        $metafile = $this->workdir . '/.phpdot/build/metafile.json';
        mkdir(dirname($metafile), 0755, true);
        file_put_contents($metafile, '{"outputs": {"./app.js": {"entryPoint"');

        $exit = $this->fake->bun->build('app.ts');

        self::assertSame(1, $exit, 'a corrupt metafile after a 0-exit build must surface as failure');
        self::assertFileDoesNotExist($this->workdir . '/public/build/manifest.json');
    }

    public function testWorkingDirScopesPackageCommandsButNotBuild(): void
    {
        $fake = new TestBun(workingDir: 'resources');

        try {
            $fake->bun->install(['ejs']);
            self::assertSame('resources', $fake->runner->passthroughCalls[0]['cwd'], 'install defaults to workingDir');

            $fake->bun->build('resources/js/app.ts');
            self::assertNull($fake->runner->passthroughCalls[1]['cwd'], 'build is project-relative, not scoped to workingDir');

            $fake->bun->install(['vue'], cwd: 'elsewhere');
            self::assertSame('elsewhere', $fake->runner->passthroughCalls[2]['cwd'], 'an explicit cwd overrides workingDir');
        } finally {
            $fake->cleanup();
        }
    }
}
