<?php

declare(strict_types=1);

/**
 * Immutable description of a `bun build` invocation, mapped to the binary's CLI flags.
 *
 * Verified against Bun 1.3.14: value flags use the `--flag=value` form; `--define`/`--drop`/
 * `--loader` are accepted though absent from `bun build --help`; hashed names are expressed via
 * `--entry-naming` (an explicit entryNaming pattern wins over the hashedNames preset).
 *
 * Deliberately NOT covered (boundaries, not gaps): the `--compile` family and every `--windows-*`
 * flag (standalone executables are a different product), the EXPERIMENTAL `--app` /
 * `--server-components` / `--react-fast-refresh` surfaces, `--production` (the wrapper's own
 * production preset plays that role), and the `--bytecode` / `--no-bundle` /
 * `--emit-dce-annotations` niches.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Build;

final readonly class BuildOptions
{
    /**
     * Holds the resolved flag set for a single bun build invocation.
     *
     * @param string|null $target browser|bun|node
     * @param string|null $format esm|cjs|iife
     * @param string|null $sourcemap linked|inline|external|none
     * @param list<string> $define K=V pairs
     * @param list<string> $external package names to keep external
     * @param list<string> $drop identifiers to drop (e.g. console, debugger)
     * @param ?string $outDir
     * @param ?string $outFile
     * @param bool $minify
     * @param bool $minifySyntax
     * @param bool $minifyWhitespace
     * @param bool $minifyIdentifiers
     * @param bool $splitting
     * @param bool $hashedNames
     * @param ?string $chunkNaming
     * @param ?string $assetNaming
     * @param ?string $metafile
     * @param ?string $banner
     * @param ?string $footer
     * @param bool $watch
     * @param ?string $root root directory for computing multi-entry output paths
     * @param ?string $publicPath prefix prepended to import paths in bundled code
     * @param ?string $entryNaming entry filename pattern; wins over the hashedNames preset
     * @param bool $cssChunking chunk CSS shared between entrypoints
     * @param bool $keepNames preserve function/class names under minification
     * @param bool $rejectUnresolved fail the build on unresolvable dynamic imports
     * @param 'external'|'bundle'|null $packages dependency handling in one flag
     * @param list<string> $loader per-extension loaders as ".ext:loader" pairs
     * @param list<string> $conditions custom package.json export conditions
     * @param ?string $env inline env vars: 'inline', 'disable', or a prefix like "PUBLIC_*"
     * @param ?string $metafileMd write the module-graph markdown to this path
     * @param bool $noClearScreen keep the terminal scrollback in watch mode
     */
    public function __construct(
        public null|string $outDir = null,
        public null|string $outFile = null,
        public null|string $target = null,
        public null|string $format = null,
        public bool $minify = false,
        public bool $minifySyntax = false,
        public bool $minifyWhitespace = false,
        public bool $minifyIdentifiers = false,
        public bool $splitting = false,
        public null|string $sourcemap = null,
        public bool $hashedNames = false,
        public null|string $chunkNaming = null,
        public null|string $assetNaming = null,
        public null|string $metafile = null,
        public array $define = [],
        public array $external = [],
        public null|string $banner = null,
        public null|string $footer = null,
        public array $drop = [],
        public bool $watch = false,
        public null|string $root = null,
        public null|string $publicPath = null,
        public null|string $entryNaming = null,
        public bool $cssChunking = false,
        public bool $keepNames = false,
        public bool $rejectUnresolved = false,
        public null|string $packages = null,
        public array $loader = [],
        public array $conditions = [],
        public null|string $env = null,
        public null|string $metafileMd = null,
        public bool $noClearScreen = false,
    ) {}

    /**
     * The `bun build` flags (excluding entrypoints) in a stable order.
     *
     * @return list<string>
     */
    public function toArguments(): array
    {
        $args = [];

        if ($this->outDir !== null) {
            $args[] = '--outdir=' . $this->outDir;
        }
        if ($this->outFile !== null) {
            $args[] = '--outfile=' . $this->outFile;
        }
        if ($this->target !== null) {
            $args[] = '--target=' . $this->target;
        }
        if ($this->format !== null) {
            $args[] = '--format=' . $this->format;
        }
        if ($this->root !== null) {
            $args[] = '--root=' . $this->root;
        }
        if ($this->publicPath !== null) {
            $args[] = '--public-path=' . $this->publicPath;
        }
        if ($this->minify) {
            $args[] = '--minify';
        }
        if ($this->minifySyntax) {
            $args[] = '--minify-syntax';
        }
        if ($this->minifyWhitespace) {
            $args[] = '--minify-whitespace';
        }
        if ($this->minifyIdentifiers) {
            $args[] = '--minify-identifiers';
        }
        if ($this->keepNames) {
            $args[] = '--keep-names';
        }
        if ($this->splitting) {
            $args[] = '--splitting';
        }
        if ($this->cssChunking) {
            $args[] = '--css-chunking';
        }
        if ($this->sourcemap !== null) {
            $args[] = '--sourcemap=' . $this->sourcemap;
        }
        $entryNaming = $this->entryNaming ?? ($this->hashedNames ? '[dir]/[name]-[hash].[ext]' : null);
        if ($entryNaming !== null) {
            $args[] = '--entry-naming=' . $entryNaming;
        }

        $chunkNaming = $this->chunkNaming ?? ($this->hashedNames ? '[name]-[hash].[ext]' : null);
        if ($chunkNaming !== null) {
            $args[] = '--chunk-naming=' . $chunkNaming;
        }
        if ($this->assetNaming !== null) {
            $args[] = '--asset-naming=' . $this->assetNaming;
        }
        if ($this->metafile !== null) {
            $args[] = '--metafile=' . $this->metafile;
        }
        if ($this->metafileMd !== null) {
            $args[] = '--metafile-md=' . $this->metafileMd;
        }

        foreach ($this->define as $define) {
            $args[] = '--define=' . $define;
        }
        foreach ($this->external as $external) {
            $args[] = '--external=' . $external;
        }
        if ($this->packages !== null) {
            $args[] = '--packages=' . $this->packages;
        }
        foreach ($this->loader as $loader) {
            $args[] = '--loader=' . $loader;
        }
        foreach ($this->conditions as $condition) {
            $args[] = '--conditions=' . $condition;
        }
        if ($this->env !== null) {
            $args[] = '--env=' . $this->env;
        }
        if ($this->rejectUnresolved) {
            $args[] = '--reject-unresolved';
        }
        if ($this->banner !== null) {
            $args[] = '--banner=' . $this->banner;
        }
        if ($this->footer !== null) {
            $args[] = '--footer=' . $this->footer;
        }
        foreach ($this->drop as $drop) {
            $args[] = '--drop=' . $drop;
        }
        if ($this->watch) {
            $args[] = '--watch';
        }
        if ($this->noClearScreen) {
            $args[] = '--no-clear-screen';
        }

        return $args;
    }
}
