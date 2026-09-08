<?php

declare(strict_types=1);

/**
 * The asset pipeline — HOW a recipe is built, stage by stage.
 *
 * The pipeline knows no tool: it runs whatever steps the recipe declares —
 * installed tools through bunx, the application's own scripts through bun
 * run, each from home so the local binary is found and a global cache never
 * is — as one fail-fast task flow where the first failing step fails the
 * build and nothing after it runs. It wipes the output directory first, bundles the declared
 * entrypoints — minified and hash-named in build mode, stable-named with
 * linked sourcemaps in watch, where the recipe's watch steps run as
 * first-class side processes beside bun's own watcher, torn down together —
 * and stamps a charset declaration onto built css so served encodings cannot
 * drift. The manifest lands in the output directory, resolved against the
 * configured URL base.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Resources;

use FilesystemIterator;
use PHPdot\Bun\Build\BuildSpec;
use PHPdot\Bun\Bun;
use PHPdot\Bun\Config\BunConfig;
use PHPdot\Bun\Exception\RecipeException;
use PHPdot\Bun\Manifest\Manifest;
use PHPdot\Bun\Task\Flow;
use PHPdot\Bun\Task\Task;
use PHPdot\Container\Attribute\Singleton;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Process\Process;

#[Singleton]
final class AssetPipeline
{
    /**
     * Wire the pipeline to the toolchain and its configuration.
     *
     * @param Bun $bun The pinned Bun toolchain
     * @param BunConfig $config Home, resources, output, and URL base
     */
    public function __construct(
        private readonly Bun $bun,
        private readonly BunConfig $config,
    ) {}

    /**
     * Run the recipe through every stage.
     *
     * @param Recipe $recipe The developer's declaration of WHAT to build
     * @param bool $watch Stable names, linked sourcemaps, side watchers — the dev loop
     *
     * @throws RecipeException When the recipe declares no entrypoints
     *
     * @return int The first failing step's exit code, or 0
     */
    public function build(Recipe $recipe, bool $watch = false): int
    {
        $entries = $recipe->entryPoints();

        if ($entries === []) {
            throw RecipeException::noEntrypoints();
        }

        $this->clean();
        $tsconfig = $this->ensureTsconfig();

        $exit = $this->ensureDependencies();

        if ($exit !== 0) {
            return $exit;
        }

        $watchers = [];
        $tasks = [];

        foreach ($recipe->steps() as $step) {
            if ($watch && $step['watchArgs'] !== null) {
                $watchers[] = $this->startWatcher($step);
            }

            if ($step['args'] === [] && $step['watchArgs'] !== null) {
                continue;
            }

            $tasks[] = new Task($this->taskName($step), fn(): int => $this->runStep($step));
        }

        $flow = (new Flow($tasks))->run($this->bun);

        if (!$flow->successful()) {
            $this->stopWatchers($watchers);

            return $flow->exitCode();
        }

        try {
            $exit = $this->bundle($entries, $watch, $tsconfig);
        } finally {
            $this->stopWatchers($watchers);
        }

        if ($exit === 0 && !$watch) {
            $this->stampCharset();
        }

        return $exit;
    }

    /**
     * The manifest the last build wrote, resolving entries against the
     * configured URL base.
     */
    public function manifest(): Manifest
    {
        return new Manifest($this->config->outputDir . '/manifest.json', $this->config->assetPrefix(), $this->config->resourcesDir);
    }

    /**
     * The home tsconfig.json, provisioned on first build when absent. Bun
     * resolves bare imports by walking up from the entrypoint, and
     * home/node_modules is on no entrypoint's ancestor chain — the `paths`
     * mapping is what makes `import 'x'` in resources find it, and the
     * `@build/*` alias is how an authored entry imports a tool's intermediate
     * from home/build without naming the home path. Once written the file is
     * the developer's: they add targets, libs, strictness.
     *
     * @return string The absolute path passed as --tsconfig-override
     */
    private function ensureTsconfig(): string
    {
        $dir = $this->config->homeDir;

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        $path = $dir . '/tsconfig.json';

        if (!is_file($path)) {
            file_put_contents($path, (string) json_encode([
                'compilerOptions' => [
                    'target' => 'ES2022',
                    'module' => 'ESNext',
                    'moduleResolution' => 'bundler',
                    'lib' => ['ES2022', 'DOM', 'DOM.Iterable'],
                    'strict' => true,
                    'skipLibCheck' => true,
                    'isolatedModules' => true,
                    'noEmit' => true,
                    'paths' => ['*' => ['./node_modules/*'], '@build/*' => ['./build/*']],
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        }

        return $path;
    }

    /**
     * Repair a fresh clone before any step runs: a committed manifest whose
     * node_modules is missing installs from the lockfile; no manifest means
     * no JavaScript dependencies, and there is nothing to do.
     *
     * @return int bun install's exit code, or 0 when there is nothing to install
     */
    private function ensureDependencies(): int
    {
        $home = $this->config->homeDir;

        if (!is_file($home . '/package.json') || is_dir($home . '/node_modules')) {
            return 0;
        }

        return $this->bun->install([]);
    }

    /**
     * Wipe the output directory — a build starts from nothing.
     */
    private function clean(): void
    {
        $dir = $this->config->outputDir;

        if (!is_dir($dir)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            if (!$file instanceof SplFileInfo) {
                continue;
            }

            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
    }

    /**
     * Execute one declared step — an installed tool through bunx or the
     * application's own script through bun run — from home, so resolution
     * never leaves the project's own yard.
     *
     * @param array{kind: 'bunx'|'run', command: string, args: list<string>, watchArgs: null|list<string>} $step
     *
     * @return int The step's exit code
     */
    private function runStep(array $step): int
    {
        return $step['kind'] === 'run'
            ? $this->bun->run($step['command'], $step['args'])
            : $this->bun->x($step['command'], $step['args']);
    }

    /**
     * The flow-task label for a step: the tool's name, or the script's file
     * name under a run: prefix.
     *
     * @param array{kind: 'bunx'|'run', command: string, args: list<string>, watchArgs: null|list<string>} $step
     *
     * @return string
     */
    private function taskName(array $step): string
    {
        return $step['kind'] === 'run'
            ? 'run:' . basename($step['command'])
            : 'bunx:' . $step['command'];
    }

    /**
     * Start a step's own watcher as a side process — the tools and scripts
     * already know how to watch; the platform composes them.
     *
     * @param array{kind: 'bunx'|'run', command: string, args: list<string>, watchArgs: null|list<string>} $step
     */
    private function startWatcher(array $step): Process
    {
        $verb = $step['kind'] === 'run' ? 'run' : 'x';
        $process = new Process(
            [$this->bun->binary(), $verb, $step['command'], ...$step['args'], ...($step['watchArgs'] ?? [])],
            $this->config->homeDir,
        );
        $process->setTimeout(null);
        $process->start();

        return $process;
    }

    /**
     * Tear the side watchers down with the build they serve.
     *
     * @param list<Process> $watchers The running side processes
     */
    private function stopWatchers(array $watchers): void
    {
        foreach ($watchers as $watcher) {
            $watcher->stop(1.0, 9);
        }
    }

    /**
     * Bundle the entrypoints through the configured verb — build carries
     * minify/splitting/hashed names, watch carries stable names and linked
     * sourcemaps — both under the [ext]/ naming layout, into the configured
     * output directory, manifest distilled beside the outputs.
     *
     * @param list<string> $entries Absolute entrypoint paths
     * @param bool $watch Which verb to ride
     * @param string $tsconfig The home tsconfig that maps bare imports to home/node_modules
     *
     * @return int bun's exit code
     */
    private function bundle(array $entries, bool $watch, string $tsconfig): int
    {
        $outputDir = $this->config->outputDir;
        $root = self::commonRoot($entries);

        $configure = static function (BuildSpec $spec) use ($watch, $outputDir, $tsconfig, $root): BuildSpec {
            $hashed = !$watch;

            return $spec
                ->outDir($outputDir)
                ->tsconfig($tsconfig)
                ->root($root)
                ->entryNaming($hashed ? '[ext]/[dir]/[name]-[hash].[ext]' : '[ext]/[dir]/[name].[ext]')
                ->chunkNaming('js/chunk-[hash].[ext]')
                ->assetNaming('assets/[name]-[hash].[ext]');
        };

        if ($watch) {
            return $this->bun->watch($entries, $configure, cwd: $this->config->homeDir);
        }

        return $this->bun->build($entries, $configure, cwd: $this->config->homeDir);
    }

    /**
     * The deepest directory every entry shares — the root [dir] is relative to, so an output
     * carries the segments below it: surface, app and module stay in the URL instead of
     * collapsing into the basename, where two apps' same-named modules would be
     * indistinguishable.
     *
     * @param list<string> $entries Absolute entrypoint paths
     *
     * @return string
     */
    private static function commonRoot(array $entries): string
    {
        $shared = explode('/', dirname((string) reset($entries)));

        foreach (array_slice($entries, 1) as $entry) {
            $segments = explode('/', dirname($entry));
            $keep = [];

            foreach ($segments as $index => $segment) {
                if (!isset($shared[$index]) || $shared[$index] !== $segment) {
                    break;
                }

                $keep[] = $segment;
            }

            $shared = $keep;
        }

        return implode('/', $shared);
    }

    /**
     * Prepend a charset declaration to every built stylesheet. A served css
     * file with no declared encoding inherits the referring document's —
     * mojibake that appears only sometimes, whenever a document's encoding
     * differs. The declaration must lead the file to count.
     *
     * Public as a stage: the pipeline runs it after a successful build, and a
     * step-by-step caller may run it alone.
     */
    public function stampCharset(): void
    {
        $dir = $this->config->outputDir;

        if (!is_dir($dir)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'css') {
                continue;
            }

            $css = @file_get_contents($file->getPathname());

            if ($css === false || $css === '' || str_starts_with($css, '@charset')) {
                continue;
            }

            file_put_contents($file->getPathname(), '@charset "UTF-8";' . $css);
        }
    }
}
