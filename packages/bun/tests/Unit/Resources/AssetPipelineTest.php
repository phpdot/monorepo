<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Unit\Resources;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPdot\Bun\Bun;
use PHPdot\Bun\Config\BunConfig;
use PHPdot\Bun\Exception\RecipeException;
use PHPdot\Bun\Process\ProcessResult;
use PHPdot\Bun\Registry\NpmRegistryClient;
use PHPdot\Bun\Resources\AssetPipeline;
use PHPdot\Bun\Resources\Recipe;
use PHPdot\Bun\Runtime\BinaryDownloader;
use PHPdot\Bun\Runtime\BinaryResolver;
use PHPdot\Bun\Runtime\PlatformDetector;
use PHPdot\Bun\Runtime\RuntimeLock;
use PHPdot\Bun\Tests\Support\FakeHttpClient;
use PHPdot\Bun\Tests\Support\FakeProcessRunner;
use PHPdot\Bun\Tests\Support\TarGz;
use PHPdot\Bun\Tests\Support\TestBun;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AssetPipelineTest extends TestCase
{
    private string $root;

    private TestBun $fake;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/phpdot-bun-pipeline-' . bin2hex(random_bytes(4));
        $this->fake = new TestBun(root: $this->root);
        mkdir($this->root . '/public/build/js', 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->fake->cleanup();
        $this->removeTree($this->root);
    }

    #[Test]
    public function aRecipeWithoutEntrypointsIsRejectedLoudly(): void
    {
        $pipeline = $this->pipeline();

        $this->expectException(RecipeException::class);
        $this->expectExceptionMessage('no entrypoints');

        $pipeline->build(new Recipe($this->root));
    }

    #[Test]
    public function theOutputDirectoryIsWipedBeforeEveryBuild(): void
    {
        $stale = $this->root . '/public/build/js/stale.js';
        file_put_contents($stale, 'old');

        $this->pipeline()->build($this->recipeWithEntry());

        self::assertFileDoesNotExist($stale, 'a build starts from nothing');
    }

    #[Test]
    public function declaredStepsRunThroughBunxFromHomeInOrder(): void
    {
        $recipe = $this->recipeWithEntry();
        $recipe->bunx('any-tool', ['-i', 'in.css', '-o', 'out.css']);
        $recipe->bunx('another-tool');

        $this->pipeline()->build($recipe);

        $calls = $this->fake->runner->passthroughCalls;

        self::assertSame('x', $calls[0]['args'][0]);
        self::assertSame('any-tool', $calls[0]['args'][1]);
        self::assertSame(['-i', 'in.css', '-o', 'out.css'], array_slice($calls[0]['args'], 2));
        self::assertSame($this->fake->home, $calls[0]['cwd'], 'tools run from home so the local binary is found');

        self::assertSame('another-tool', $calls[1]['args'][1], 'steps run in declaration order');

        self::assertSame('build', $calls[2]['args'][0], 'the bundle follows the steps');
    }

    #[Test]
    public function aFailingStepStopsThePipelineBeforeBundling(): void
    {
        $failing = new TestBun(
            runner: new FakeProcessRunner(default: new ProcessResult(0, "1.4.0\n", ''), passthroughExit: 1),
            root: $this->root . '-failing',
        );

        $recipe = new Recipe($failing->home);
        $recipe->entries($this->root . '-failing/resources/platform.ts');
        $recipe->bunx('broken-tool');

        $exit = (new AssetPipeline($failing->bun, $failing->config))->build($recipe);

        self::assertSame(1, $exit);

        $calls = $failing->runner->passthroughCalls;
        self::assertCount(1, $calls, 'the bundle never runs after a failing step');

        $failing->cleanup();
        $this->removeTree($this->root . '-failing');
    }

    #[Test]
    public function bundleEntriesCarryAbsolutePathsHomeCwdAndTheConfiguredOutput(): void
    {
        $entry = $this->root . '/resources/platform.ts';

        $this->pipeline()->build($this->recipeWithEntry($entry));

        $call = $this->fake->runner->passthroughCalls[0];

        self::assertSame($entry, $call['args'][1]);
        self::assertSame($this->fake->home, $call['cwd']);
        self::assertContains('--outdir=' . $this->root . '/public/build', $call['args'], 'output lands in the configured dir, not relative to home');
    }

    #[Test]
    public function builtStylesheetsGainALeadingCharsetDeclaration(): void
    {
        mkdir($this->root . '/public/build/css', 0o755, true);
        file_put_contents($this->root . '/public/build/css/app.css', "body { margin: 0 }\n");

        $this->pipeline()->stampCharset();

        self::assertSame('@charset "UTF-8";body { margin: 0 }' . "\n", (string) file_get_contents($this->root . '/public/build/css/app.css'));
    }

    #[Test]
    public function theManifestResolvesAgainstTheConfiguredPrefix(): void
    {
        $pipeline = new AssetPipeline(
            $this->fake->bun,
            new BunConfig(
                resourcesDir: $this->root . '/resources',
                outputDir: $this->root . '/public/build',
                baseUrl: 'https://cdn.example.com/build',
            ),
        );

        $manifestPath = $this->root . '/public/build/manifest.json';
        file_put_contents(
            $manifestPath,
            (string) json_encode(['outputs' => ['js/app-BX.js' => ['entryPoint' => '/resources/app.ts']]]),
        );

        self::assertSame('https://cdn.example.com/build/js/app-BX.js', $pipeline->manifest()->js('/resources/app.ts'));
    }

    #[Test]
    public function aNullBaseUrlDerivesThePrefixFromTheOutputDirectory(): void
    {
        $config = new BunConfig(
            resourcesDir: $this->root . '/resources',
            outputDir: $this->root . '/public/build',
        );

        self::assertSame('/build', $config->assetPrefix());
    }

    #[Test]
    public function watchModeUsesStableNamesAndRunsWatchStepsAsSideProcesses(): void
    {
        $recipe = $this->recipeWithEntry();
        $recipe->bunx('one-shot-tool');
        $recipe->bunxWatch('watching-tool', ['--watch']);

        $this->pipeline()->build($recipe, watch: true);

        $calls = $this->fake->runner->passthroughCalls;
        $tools = array_map(static fn(array $call): string => $call['args'][1] ?? '', $calls);

        self::assertContains('one-shot-tool', $tools, 'a one-shot step still runs once in watch mode');
        self::assertNotContains('watching-tool', $tools, 'a watch step is a side process, not a passthrough');

        $bundle = $calls[array_key_last($calls)];
        self::assertSame('build', $bundle['args'][0]);
        self::assertContains('--watch', $bundle['args'], 'the bundle rides the watch verb');
        self::assertContains('--entry-naming=[ext]/[dir]/[name].[ext]', $bundle['args'], 'stable names: the manifest bun never rewrites in watch stays valid');
        self::assertNotContains('--entry-naming=[ext]/[dir]/[name]-[hash].[ext]', $bundle['args']);
    }

    #[Test]
    public function buildModeUsesHashedNames(): void
    {
        $this->pipeline()->build($this->recipeWithEntry());

        $bundle = $this->fake->runner->passthroughCalls[0];

        self::assertNotContains('--watch', $bundle['args']);
        self::assertContains('--entry-naming=[ext]/[dir]/[name]-[hash].[ext]', $bundle['args'], 'hashed names bust caches on every build');
    }

    #[Test]
    public function aHomeTsconfigIsProvisionedOnceAndPassedAsTheOverride(): void
    {
        $tsconfig = $this->fake->home . '/tsconfig.json';
        self::assertFileDoesNotExist($tsconfig);

        $this->pipeline()->build($this->recipeWithEntry());

        self::assertFileExists($tsconfig, 'provisioned on first build');
        $written = json_decode((string) file_get_contents($tsconfig), true);
        self::assertSame(['./node_modules/*'], $written['compilerOptions']['paths']['*'], 'bare imports map to home/node_modules');
        self::assertSame(['./build/*'], $written['compilerOptions']['paths']['@build/*'], 'authored entries reach tool intermediates through the alias');

        $bundle = $this->fake->runner->passthroughCalls[0];
        self::assertContains('--tsconfig-override=' . $tsconfig, $bundle['args'], 'bun is told which tsconfig to use');

        file_put_contents($tsconfig, '{"compilerOptions":{"strict":false}}');
        $this->pipeline()->build($this->recipeWithEntry());
        self::assertSame('{"compilerOptions":{"strict":false}}', (string) file_get_contents($tsconfig), 'an existing tsconfig belongs to the developer — never overwritten');
    }

    #[Test]
    public function aBuildOnAFreshProjectCreatesHomeDirAndProvisionsTheTsconfigItself(): void
    {
        $root = sys_get_temp_dir() . '/phpdot-bun-fresh-' . bin2hex(random_bytes(4));
        $runner = new FakeProcessRunner(default: new ProcessResult(0, "1.4.0\n", ''));
        $config = new BunConfig(resourcesDir: $root . '/resources', outputDir: $root . '/public/build');

        $factory = new Psr17Factory();
        $http = new FakeHttpClient();
        $platform = (new PlatformDetector($runner))->detect();
        $tgz = TarGz::build(['package/bin/' . $platform->binaryFilename() => 'BUN-BINARY']);
        $tarball = 'https://example.test/bun.tgz';
        $http->map(
            'https://registry.npmjs.org/' . $platform->npmPackage(),
            (string) json_encode(['versions' => ['1.4.0' => ['dist' => ['tarball' => $tarball, 'integrity' => TarGz::integrity($tgz)]]]]),
        );
        $http->map($tarball, $tgz);

        $downloader = new BinaryDownloader($http, $factory, new NpmRegistryClient($http, $factory, $config));
        $resolver = new BinaryResolver($config, new PlatformDetector($runner), $downloader, $runner, new RuntimeLock());
        $bun = new Bun($resolver, $runner, $config);

        $recipe = new Recipe($config->homeDir);
        $recipe->entries($root . '/resources/platform.ts');

        $exit = (new AssetPipeline($bun, $config))->build($recipe);

        self::assertSame(0, $exit);
        self::assertFileExists($config->homeDir . '/tsconfig.json', 'the first build creates homeDir itself — no package verb has to run first');

        $this->removeTree($root);
    }

    #[Test]
    public function aWatchArmedStepRunsOnceWithoutItsWatchArgsInBuildMode(): void
    {
        $recipe = $this->recipeWithEntry();
        $recipe->bunx('@tailwindcss/cli', ['-i', 'in.css', '-o', 'out.css'], watch: ['--watch']);

        $this->pipeline()->build($recipe, watch: false);

        $step = $this->fake->runner->passthroughCalls[0];
        self::assertSame('@tailwindcss/cli', $step['args'][1]);
        self::assertNotContains('--watch', $step['args'], 'the watch flag never leaks into a one-shot build — a blocking tool would hang');
        self::assertSame(['-i', 'in.css', '-o', 'out.css'], array_slice($step['args'], 2));
    }

    #[Test]
    public function aWatchArmedStepAppendsItsWatchArgsAsASideProcessInWatchMode(): void
    {
        $recipe = $this->recipeWithEntry();
        $recipe->bunx('@tailwindcss/cli', ['-i', 'in.css'], watch: ['--watch']);

        $this->pipeline()->build($recipe, watch: true);

        $step = $this->fake->runner->passthroughCalls[0];
        self::assertSame('@tailwindcss/cli', $step['args'][1], 'the initial one-shot still runs in watch mode');
        self::assertNotContains('--watch', $step['args'], 'the one-shot compile carries no watch flag — the side watcher does');
    }

    #[Test]
    public function scriptStepsRunThroughBunRunFromHome(): void
    {
        $recipe = $this->recipeWithEntry();
        $recipe->run($this->fake->home . '/build/extract-icons.mjs', ['-i', 'svg']);

        $this->pipeline()->build($recipe);

        $call = $this->fake->runner->passthroughCalls[0];
        self::assertSame('run', $call['args'][0]);
        self::assertSame($this->fake->home . '/build/extract-icons.mjs', $call['args'][1]);
        self::assertSame(['-i', 'svg'], array_slice($call['args'], 2));
        self::assertSame($this->fake->home, $call['cwd'], 'scripts run from home so their imports resolve locally');
    }

    #[Test]
    public function aFailingScriptStepFailsTheBuildAndNothingAfterItRuns(): void
    {
        $failing = new TestBun(
            runner: new FakeProcessRunner(default: new ProcessResult(0, "1.4.0\n", ''), passthroughExit: 1),
            root: $this->root . '-failing-script',
        );

        $recipe = new Recipe($failing->home);
        $recipe->entries($this->root . '-failing-script/resources/platform.ts');
        $recipe->run($failing->home . '/build/gen.mjs');
        $recipe->bunx('never-reached-tool');

        $exit = (new AssetPipeline($failing->bun, $failing->config))->build($recipe);

        self::assertSame(1, $exit);
        self::assertCount(1, $failing->runner->passthroughCalls, 'the first failing step stops the flow — later steps and the bundle never run');

        $failing->cleanup();
        $this->removeTree($this->root . '-failing-script');
    }

    #[Test]
    public function aCommittedManifestWithMissingNodeModulesInstallsFromTheLockfileFirst(): void
    {
        file_put_contents($this->fake->home . '/package.json', '{"dependencies":{"nanoid":"^5"}}');
        $recipe = $this->recipeWithEntry();
        $recipe->bunx('a-tool');

        $this->pipeline()->build($recipe);

        $install = $this->fake->runner->passthroughCalls[0];
        self::assertSame(['install'], $install['args'], 'a fresh clone installs from the committed lockfile before any step');
        self::assertSame($this->fake->home, $install['cwd']);
        self::assertSame('x', $this->fake->runner->passthroughCalls[1]['args'][0], 'the steps follow the repair');
    }

    #[Test]
    public function anExistingNodeModulesSkipsTheLockfileInstall(): void
    {
        file_put_contents($this->fake->home . '/package.json', '{"dependencies":{}}');
        mkdir($this->fake->home . '/node_modules');
        $recipe = $this->recipeWithEntry();
        $recipe->bunx('a-tool');

        $this->pipeline()->build($recipe);

        self::assertSame('x', $this->fake->runner->passthroughCalls[0]['args'][0], 'no install runs when node_modules already exists');
    }

    #[Test]
    public function aFailedLockfileInstallFailsTheBuildBeforeAnyStep(): void
    {
        $failing = new TestBun(
            runner: new FakeProcessRunner(default: new ProcessResult(0, "1.4.0\n", ''), passthroughExit: 1),
            root: $this->root . '-failing-install',
        );
        file_put_contents($failing->home . '/package.json', '{"dependencies":{}}');

        $recipe = new Recipe($failing->home);
        $recipe->entries($this->root . '-failing-install/resources/platform.ts');
        $recipe->bunx('never-reached-tool');

        $exit = (new AssetPipeline($failing->bun, $failing->config))->build($recipe);

        self::assertSame(1, $exit, 'a failed lockfile install fails the build');
        $calls = $failing->runner->passthroughCalls;
        self::assertSame(['install'], $calls[0]['args']);
        self::assertCount(1, $calls, 'no step runs after a failed install');

        $failing->cleanup();
        $this->removeTree($this->root . '-failing-install');
    }

    #[Test]
    public function aWatchArmedScriptStepRunsOnceWithoutItsWatchArgsInBuildMode(): void
    {
        $recipe = $this->recipeWithEntry();
        $recipe->run($this->fake->home . '/build/watch-icons.mjs', ['-i', 'svg'], watch: ['--watch']);

        $this->pipeline()->build($recipe, watch: false);

        $step = $this->fake->runner->passthroughCalls[0];
        self::assertSame('run', $step['args'][0]);
        self::assertSame($this->fake->home . '/build/watch-icons.mjs', $step['args'][1]);
        self::assertNotContains('--watch', $step['args'], 'the watch flag never reaches a one-shot build');
    }

    private function pipeline(): AssetPipeline
    {
        return new AssetPipeline($this->fake->bun, $this->fake->config);
    }

    private function recipeWithEntry(null|string $entry = null): Recipe
    {
        $recipe = new Recipe($this->fake->home);
        $recipe->entries($entry ?? $this->root . '/resources/platform.ts');

        return $recipe;
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeTree($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
