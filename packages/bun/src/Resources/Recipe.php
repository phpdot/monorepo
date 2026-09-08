<?php

declare(strict_types=1);

/**
 * The developer's asset recipe — WHAT to build, never HOW.
 *
 * A recipe is a file at `<resourcesDir>/build.php` returning
 * `callable(Recipe): void`. It declares entrypoints in whatever shape the
 * application has and any steps the pipeline runs before bundling —
 * tailwind, postcss, sass, an extractor script, anything: the package knows
 * no tool, only how to run steps safely (bunx for installed tools, bun run
 * for the application's own scripts — from home, the local binary, never
 * a global cache).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Resources;

final class Recipe
{
    /**
     * @var list<string> Absolute entrypoint paths, in declaration order
     */
    private array $entries = [];

    /**
     * @var list<array{kind: 'bunx'|'run', command: string, args: list<string>, watchArgs: null|list<string>}> Steps, in declaration order
     */
    private array $steps = [];

    /**
     * @param string $homeDir The absolute home the bridge files are written under
     */
    public function __construct(
        private readonly string $homeDir,
    ) {}

    /**
     * Declare bundle entrypoints. Every call appends; the pipeline builds them all.
     *
     * @param string ...$paths Absolute entrypoint paths — the recipe file knows the root via dirname(__DIR__)
     */
    public function entries(string ...$paths): void
    {
        foreach ($paths as $path) {
            $this->entries[] = $path;
        }
    }

    /**
     * Run a tool through bunx from home before bundling.
     *
     * Without `watch`, the step runs once in both build and watch mode — a code
     * generator, a font subsetter, anything whose output is consumed once. With
     * `watch`, the step runs once in build mode with `$args` alone, and in watch
     * mode becomes a long-lived side process invoked with `$args` followed by
     * `$watch` — the tool's own watcher, beside bun's, torn down together. The
     * watch flag never leaks into a build.
     *
     * @param string $tool The tool bunx resolves from home's node_modules
     * @param list<string> $args The tool's build-and-watch arguments
     * @param null|list<string> $watch Extra arguments appended only in watch mode (e.g. `['--watch']`)
     */
    public function bunx(string $tool, array $args = [], null|array $watch = null): void
    {
        $this->steps[] = ['kind' => 'bunx', 'command' => $tool, 'args' => $args, 'watchArgs' => $watch];
    }

    /**
     * A tool whose only role is to watch — nothing runs in build mode, and in
     * watch mode it is a side process with `$args`. Shorthand for a step with an
     * empty build phase.
     *
     * @param string $tool The tool bunx resolves from home's node_modules
     * @param list<string> $args The watcher's arguments, including its watch flag
     */
    public function bunxWatch(string $tool, array $args = []): void
    {
        $this->steps[] = ['kind' => 'bunx', 'command' => $tool, 'args' => [], 'watchArgs' => $args];
    }

    /**
     * Run one of the application's own scripts through `bun run` from home
     * before bundling — an icon extractor, a code generator, anything authored
     * by the application rather than installed from npm.
     *
     * The same watch contract as {@see bunx()}: without `watch` the script runs
     * once in both build and watch mode; with `watch` it runs once in build mode
     * with `$args` alone, and in watch mode also stays running with `$watch`
     * appended, beside bun's own watcher. A script that imports npm packages
     * must live under home — stage it with {@see bridge()} — because from
     * elsewhere bun silently resolves its bare imports from the public registry.
     *
     * @param string $script Absolute script path — under home when it imports npm packages
     * @param list<string> $args The script's build-and-watch arguments
     * @param null|list<string> $watch Extra arguments appended only in watch mode
     */
    public function run(string $script, array $args = [], null|array $watch = null): void
    {
        $this->steps[] = ['kind' => 'run', 'command' => $script, 'args' => $args, 'watchArgs' => $watch];
    }

    /**
     * A script whose only role is to watch — the {@see run()} counterpart of
     * {@see bunxWatch()}: nothing runs in build mode, and in watch mode the
     * script is a side process with `$args`.
     *
     * @param string $script Absolute script path
     * @param list<string> $args The script's arguments, including its watch flag
     */
    public function runWatch(string $script, array $args = []): void
    {
        $this->steps[] = ['kind' => 'run', 'command' => $script, 'args' => [], 'watchArgs' => $args];
    }

    /**
     * Write a bridge file into home/build and return its absolute path — for
     * a tool that resolves bare imports from the input file's own directory:
     * the bridge lives beside home/node_modules, names the package, and pulls
     * the authored source in by absolute path. The authored file itself stays
     * in resources, untouched and package-free.
     *
     * @param string $name The file name under home/build
     * @param string $content The bridge content — the recipe's, naming its own tool
     *
     * @return string The absolute path of the written bridge
     */
    public function bridge(string $name, string $content): string
    {
        $dir = $this->homeDir . '/build';

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        $path = $dir . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * The build directory inside home — where bridges and tool intermediates live.
     *
     * @return string
     */
    public function buildDir(): string
    {
        return $this->homeDir . '/build';
    }

    /**
     * The declared entrypoints.
     *
     * @return list<string>
     */
    public function entryPoints(): array
    {
        return $this->entries;
    }

    /**
     * The declared steps, in order — tools and scripts alike.
     *
     * @return list<array{kind: 'bunx'|'run', command: string, args: list<string>, watchArgs: null|list<string>}>
     */
    public function steps(): array
    {
        return $this->steps;
    }
}
