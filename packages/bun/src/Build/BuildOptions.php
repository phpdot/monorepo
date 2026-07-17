<?php

declare(strict_types=1);

/**
 * Immutable description of a `bun build` invocation, mapped to the binary's CLI flags.
 *
 * Verified against Bun 1.3.14: value flags use the `--flag=value` form; `--define`/`--drop` are
 * accepted though absent from `bun build --help`; `--hashed-names` is expressed via `--entry-naming`.
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
     */
    public function __construct(
        public ?string $outDir = null,
        public ?string $outFile = null,
        public ?string $target = null,
        public ?string $format = null,
        public bool $minify = false,
        public bool $minifySyntax = false,
        public bool $minifyWhitespace = false,
        public bool $minifyIdentifiers = false,
        public bool $splitting = false,
        public ?string $sourcemap = null,
        public bool $hashedNames = false,
        public ?string $chunkNaming = null,
        public ?string $assetNaming = null,
        public ?string $metafile = null,
        public array $define = [],
        public array $external = [],
        public ?string $banner = null,
        public ?string $footer = null,
        public array $drop = [],
        public bool $watch = false,
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
        if ($this->splitting) {
            $args[] = '--splitting';
        }
        if ($this->sourcemap !== null) {
            $args[] = '--sourcemap=' . $this->sourcemap;
        }
        if ($this->hashedNames) {
            $args[] = '--entry-naming=[dir]/[name]-[hash].[ext]';
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

        foreach ($this->define as $define) {
            $args[] = '--define=' . $define;
        }
        foreach ($this->external as $external) {
            $args[] = '--external=' . $external;
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

        return $args;
    }
}
