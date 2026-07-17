<?php

declare(strict_types=1);

/**
 * The package's public surface: an injected service that wraps the Bun binary. Resolve it from the
 * container and call its methods (`$bun->install(...)`, `$bun->run(...)`). There is no static entry
 * point or facade.
 *
 * Each call resolves the binary (downloading it on first use) and delegates to the Bun CLI,
 * streaming output through to the console.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun;

use PHPdot\Bun\Build\BuildOptions;
use PHPdot\Bun\Build\BuildSpec;
use PHPdot\Bun\Config\BunConfig;
use PHPdot\Bun\Manifest\Manifest;
use PHPdot\Bun\Process\ProcessRunnerInterface;
use PHPdot\Bun\Runtime\BinaryResolver;
use PHPdot\Container\Attribute\Singleton;

#[Singleton]
final class Bun
{
    /**
     * Private, project-relative path for the verbose metafile bun emits. Kept outside the output dir
     * (often the web root): it is distilled into a deploy-safe manifest, then deleted.
     */
    private const string THROWAWAY_METAFILE = '.phpdot/build/metafile.json';

    /**
     * Wire the Bun facade to its binary resolver, process runner, and config.
     *
     * @param BinaryResolver $resolver
     * @param ProcessRunnerInterface $process
     * @param BunConfig $config
     */
    public function __construct(
        private readonly BinaryResolver $resolver,
        private readonly ProcessRunnerInterface $process,
        private readonly BunConfig $config,
    ) {}

    /**
     * Install packages: `bun add [--dev] <pkg...>`. Auto-creates package.json + lockfile.
     *
     * @param list<string> $packages
     * @param bool $dev
     * @param ?string $cwd
     *
     * @return int
     */
    public function install(array $packages, bool $dev = false, ?string $cwd = null): int
    {
        $args = $dev ? ['add', '--dev', ...$packages] : ['add', ...$packages];

        return $this->passthrough($args, $this->workingDir($cwd));
    }

    /**
     * Remove packages: `bun remove <pkg...>`.
     *
     * @param list<string> $packages
     * @param ?string $cwd
     *
     * @return int
     */
    public function remove(array $packages, ?string $cwd = null): int
    {
        return $this->passthrough(['remove', ...$packages], $this->workingDir($cwd));
    }

    /**
     * Show package metadata: `bun pm view <pkg>`.
     *
     * @param string $package
     * @param ?string $cwd
     *
     * @return int
     */
    public function view(string $package, ?string $cwd = null): int
    {
        return $this->passthrough(['pm', 'view', $package], $this->workingDir($cwd));
    }

    /**
     * Run a package.json script: `bun run <script> [args...]`. May be long-lived (dev server).
     *
     * @param list<string> $args
     * @param string $script
     * @param ?string $cwd
     *
     * @return int
     */
    public function run(string $script, array $args = [], ?string $cwd = null): int
    {
        return $this->passthrough(['run', $script, ...$args], $this->workingDir($cwd));
    }

    /**
     * Run an installed CLI tool: `bun x <tool> [args...]`. The supported path for build-step tools.
     *
     * @param list<string> $args
     * @param string $tool
     * @param ?string $cwd
     *
     * @return int
     */
    public function x(string $tool, array $args = [], ?string $cwd = null): int
    {
        return $this->passthrough(['x', $tool, ...$args], $this->workingDir($cwd));
    }

    /**
     * Bundle entrypoint(s) with production defaults — minify, code splitting, content-hashed names —
     * and distil a deploy-safe `manifest.json` into the output dir. Pass a closure to adjust the
     * {@see BuildSpec}. The manifest (and throwaway-metafile cleanup) is handled by {@see buildWith()}.
     *
     * @param string|list<string> $entrypoints
     * @param (callable(BuildSpec): BuildSpec)|null $configure
     * @param ?string $cwd
     *
     * @return int
     */
    public function build(string|array $entrypoints, ?callable $configure = null, ?string $cwd = null): int
    {
        $spec = (new BuildSpec())
            ->outDir('public/build')
            ->target('browser')
            ->minify()
            ->splitting()
            ->hashedNames();

        if ($configure !== null) {
            $spec = $configure($spec);
        }

        return $this->buildWith($this->entrypoints($entrypoints), $spec->toOptions(), $cwd);
    }

    /**
     * Bundle in watch mode for development: no minification, sourcemaps on, rebuilds on change.
     * Long-lived — streams output and exits cleanly on SIGINT/SIGTERM.
     *
     * @param string|list<string> $entrypoints
     * @param (callable(BuildSpec): BuildSpec)|null $configure
     * @param ?string $cwd
     *
     * @return int
     */
    public function watch(string|array $entrypoints, ?callable $configure = null, ?string $cwd = null): int
    {
        $spec = (new BuildSpec())
            ->outDir('public/build')
            ->target('browser')
            ->splitting()
            ->sourcemap('linked')
            ->watch();

        return $this->buildWith($this->entrypoints($entrypoints), $this->configure($spec, $configure), $cwd);
    }

    /**
     * Run `bun build` with an explicit, fully-formed set of options (used by the build command, which
     * maps CLI flags directly rather than applying the production defaults).
     *
     * For any one-shot build that has an output dir this also distils a deploy-safe manifest into
     * `<outDir>/manifest.json`. When the caller didn't request a metafile, a private throwaway
     * (outside the output dir) is used solely to produce the manifest and then removed — so the
     * verbose metafile (absolute paths + module graph) is never left in or served from the web root.
     * Watch builds are long-lived and skip this. Returns bun's exit code, or a non-zero code if the
     * build succeeded but its manifest could not be written.
     *
     * @param list<string> $entrypoints
     * @param BuildOptions $options
     * @param ?string $cwd
     *
     * @return int
     */
    public function buildWith(array $entrypoints, BuildOptions $options, ?string $cwd = null): int
    {
        $outDir = $options->outDir;
        $explicitMetafile = $options->metafile;

        $metafile = $explicitMetafile;
        if (!$options->watch && $outDir !== null && $metafile === null) {
            $metafile = self::THROWAWAY_METAFILE;
        }

        $args = $options->toArguments();
        if ($explicitMetafile === null && $metafile !== null) {
            $args[] = '--metafile=' . $metafile;
        }

        $exit = $this->passthrough(['build', ...$entrypoints, ...$args], $cwd);

        if ($exit !== 0 || $options->watch || $outDir === null || $metafile === null) {
            return $exit;
        }

        $metafilePath = $this->underCwd($cwd, $metafile);
        if (!is_file($metafilePath)) {
            return $exit;
        }

        $compiled = Manifest::compile($metafilePath, $this->underCwd($cwd, $outDir . '/manifest.json'));

        if ($explicitMetafile === null) {
            @unlink($metafilePath);
        }

        return $compiled ? $exit : 1;
    }

    /**
     * Resolve the working directory for a package-context command: an explicit cwd wins, otherwise
     * the configured workingDir (which may be null = current dir).
     *
     * @param ?string $cwd
     *
     * @return ?string
     */
    private function workingDir(?string $cwd): ?string
    {
        return $cwd ?? $this->config->workingDir;
    }

    /**
     * Normalizes one or more entrypoints into a list.
     *
     * @param string|list<string> $entrypoints
     *
     * @return list<string>
     */
    private function entrypoints(string|array $entrypoints): array
    {
        return is_array($entrypoints) ? $entrypoints : [$entrypoints];
    }

    /**
     * Resolve a relative path against the build's working directory, so the PHP-side manifest write
     * lands where Bun wrote the metafile.
     *
     * @param string $path
     * @param ?string $cwd
     *
     * @return string
     */
    private function underCwd(?string $cwd, string $path): string
    {
        if ($cwd === null || str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1) {
            return $path;
        }

        return rtrim($cwd, '/') . '/' . $path;
    }

    /**
     * Applies the optional configurator to the spec and resolves it to concrete build options.
     *
     * @param (callable(BuildSpec): BuildSpec)|null $configure
     * @param BuildSpec $spec
     *
     * @return BuildOptions
     */
    private function configure(BuildSpec $spec, ?callable $configure): BuildOptions
    {
        if ($configure !== null) {
            $spec = $configure($spec);
        }

        return $spec->toOptions();
    }

    /**
     * Runs the resolved Bun binary with the given arguments, streaming its output through.
     *
     * @param list<string> $args
     * @param ?string $cwd
     *
     * @return int
     */
    private function passthrough(array $args, ?string $cwd): int
    {
        return $this->process->passthrough($this->resolver->resolve(), $args, $cwd);
    }
}
