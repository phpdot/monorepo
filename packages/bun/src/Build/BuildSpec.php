<?php

declare(strict_types=1);

/**
 * Fluent, immutable configuration for a build. Each wither returns a new spec; `toOptions()`
 * produces the {@see BuildOptions} value object consumed by {@see \PHPdot\Bun\Bun::build()}.
 *
 * This is value-object configuration, not a pipe/stream abstraction — developers reach for it only
 * when the zero-config production defaults need adjusting.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Build;

final class BuildSpec
{
    private null|string $outDir = null;
    private null|string $outFile = null;
    private null|string $target = null;
    private null|string $format = null;
    private bool $minify = false;
    private bool $minifySyntax = false;
    private bool $minifyWhitespace = false;
    private bool $minifyIdentifiers = false;
    private bool $splitting = false;
    private null|string $sourcemap = null;
    private bool $hashedNames = false;
    private null|string $chunkNaming = null;
    private null|string $assetNaming = null;
    private null|string $metafile = null;
    /**
     * @var list<string>
     */
    private array $define = [];
    /**
     * @var list<string>
     */
    private array $external = [];
    private null|string $banner = null;
    private null|string $footer = null;
    /**
     * @var list<string>
     */
    private array $drop = [];
    private bool $watch = false;
    private null|string $root = null;
    private null|string $publicPath = null;
    private null|string $entryNaming = null;
    private bool $cssChunking = false;
    private bool $keepNames = false;
    private bool $rejectUnresolved = false;
    /**
     * @var 'external'|'bundle'|null
     */
    private null|string $packages = null;
    /**
     * @var list<string>
     */
    private array $loader = [];
    /**
     * @var list<string>
     */
    private array $conditions = [];
    private null|string $env = null;
    private null|string $metafileMd = null;
    private bool $noClearScreen = false;

    /**
     * Set the output directory Bun writes the build to.
     *
     * @param string $dir
     *
     * @return self
     */
    public function outDir(string $dir): self
    {
        $c = clone $this;
        $c->outDir = $dir;

        return $c;
    }

    /**
     * Bundle everything to a single output file at the given path.
     *
     * @param string $file
     *
     * @return self
     */
    public function outFile(string $file): self
    {
        $c = clone $this;
        $c->outFile = $file;

        return $c;
    }

    /**
     * Returns a copy of the spec with the build target set (e.g. browser, bun, node).
     *
     * @param 'browser'|'bun'|'node' $target
     *
     * @return BuildSpec
     */
    public function target(string $target): self
    {
        $c = clone $this;
        $c->target = $target;

        return $c;
    }

    /**
     * Returns a copy of the spec with the output module format set (e.g. esm, cjs, iife).
     *
     * @param 'esm'|'cjs'|'iife' $format
     *
     * @return BuildSpec
     */
    public function format(string $format): self
    {
        $c = clone $this;
        $c->format = $format;

        return $c;
    }

    /**
     * Enable or disable full minification (syntax, whitespace, and identifiers).
     *
     * @param bool $minify
     *
     * @return self
     */
    public function minify(bool $minify = true): self
    {
        $c = clone $this;
        $c->minify = $minify;

        return $c;
    }

    /**
     * Disable minification.
     *
     * @return self
     */
    public function noMinify(): self
    {
        return $this->minify(false);
    }

    /**
     * Toggle syntax-only minification.
     *
     * @param bool $on
     *
     * @return self
     */
    public function minifySyntax(bool $on = true): self
    {
        $c = clone $this;
        $c->minifySyntax = $on;

        return $c;
    }

    /**
     * Toggle whitespace-only minification.
     *
     * @param bool $on
     *
     * @return self
     */
    public function minifyWhitespace(bool $on = true): self
    {
        $c = clone $this;
        $c->minifyWhitespace = $on;

        return $c;
    }

    /**
     * Toggle identifier-renaming minification.
     *
     * @param bool $on
     *
     * @return self
     */
    public function minifyIdentifiers(bool $on = true): self
    {
        $c = clone $this;
        $c->minifyIdentifiers = $on;

        return $c;
    }

    /**
     * Enable or disable code splitting into shared chunks.
     *
     * @param bool $splitting
     *
     * @return self
     */
    public function splitting(bool $splitting = true): self
    {
        $c = clone $this;
        $c->splitting = $splitting;

        return $c;
    }

    /**
     * Disable code splitting.
     *
     * @return self
     */
    public function noSplitting(): self
    {
        return $this->splitting(false);
    }

    /**
     * Returns a copy of the spec with source-map generation set (linked by default).
     *
     * @param 'linked'|'inline'|'external'|'none' $kind
     *
     * @return BuildSpec
     */
    public function sourcemap(string $kind = 'linked'): self
    {
        $c = clone $this;
        $c->sourcemap = $kind;

        return $c;
    }

    /**
     * Enable or disable content-hashed output file names.
     *
     * @param bool $hashed
     *
     * @return self
     */
    public function hashedNames(bool $hashed = true): self
    {
        $c = clone $this;
        $c->hashedNames = $hashed;

        return $c;
    }

    /**
     * Disable content-hashed output file names.
     *
     * @return self
     */
    public function noHashedNames(): self
    {
        return $this->hashedNames(false);
    }

    /**
     * Set the naming pattern for split chunks.
     *
     * @param string $pattern
     *
     * @return self
     */
    public function chunkNaming(string $pattern): self
    {
        $c = clone $this;
        $c->chunkNaming = $pattern;

        return $c;
    }

    /**
     * Set the naming pattern for emitted (non-entry) assets.
     *
     * @param string $pattern
     *
     * @return self
     */
    public function assetNaming(string $pattern): self
    {
        $c = clone $this;
        $c->assetNaming = $pattern;

        return $c;
    }

    /**
     * Write the build metafile (module graph) to the given path.
     *
     * @param string $path
     *
     * @return self
     */
    public function metafile(string $path): self
    {
        $c = clone $this;
        $c->metafile = $path;

        return $c;
    }

    /**
     * Add a compile-time global substitution, given as KEY=value.
     *
     * @param string $keyValue
     *
     * @return self
     */
    public function define(string $keyValue): self
    {
        $c = clone $this;
        $c->define[] = $keyValue;

        return $c;
    }

    /**
     * Mark a package as external so it is excluded from the bundle.
     *
     * @param string $package
     *
     * @return self
     */
    public function external(string $package): self
    {
        $c = clone $this;
        $c->external[] = $package;

        return $c;
    }

    /**
     * Prepend a literal banner to every output file.
     *
     * @param string $banner
     *
     * @return self
     */
    public function banner(string $banner): self
    {
        $c = clone $this;
        $c->banner = $banner;

        return $c;
    }

    /**
     * Append a literal footer to every output file.
     *
     * @param string $footer
     *
     * @return self
     */
    public function footer(string $footer): self
    {
        $c = clone $this;
        $c->footer = $footer;

        return $c;
    }

    /**
     * Strip calls to the named identifier (e.g. console) from the output.
     *
     * @param string $identifier
     *
     * @return self
     */
    public function drop(string $identifier): self
    {
        $c = clone $this;
        $c->drop[] = $identifier;

        return $c;
    }

    /**
     * Enable or disable watch mode (rebuild on file change).
     *
     * @param bool $watch
     *
     * @return self
     */
    public function watch(bool $watch = true): self
    {
        $c = clone $this;
        $c->watch = $watch;

        return $c;
    }


    /**
     * Set the root directory used to compute output paths for multiple entry points.
     *
     * @param string $root
     *
     * @return self
     */
    public function root(string $root): self
    {
        $c = clone $this;
        $c->root = $root;

        return $c;
    }

    /**
     * Set the prefix prepended to any import paths in bundled code (CDN or subpath serving).
     *
     * @param string $path
     *
     * @return self
     */
    public function publicPath(string $path): self
    {
        $c = clone $this;
        $c->publicPath = $path;

        return $c;
    }

    /**
     * Set an explicit entry-point filename pattern. Wins over the hashedNames preset.
     *
     * @param string $pattern
     *
     * @return self
     */
    public function entryNaming(string $pattern): self
    {
        $c = clone $this;
        $c->entryNaming = $pattern;

        return $c;
    }

    /**
     * Toggle chunking of CSS shared between multiple entrypoints.
     *
     * @param bool $on
     *
     * @return self
     */
    public function cssChunking(bool $on = true): self
    {
        $c = clone $this;
        $c->cssChunking = $on;

        return $c;
    }

    /**
     * Toggle preserving original function and class names when minifying.
     *
     * @param bool $on
     *
     * @return self
     */
    public function keepNames(bool $on = true): self
    {
        $c = clone $this;
        $c->keepNames = $on;

        return $c;
    }

    /**
     * Toggle failing the build on dynamic import()/require() specifiers that cannot be resolved.
     *
     * @param bool $on
     *
     * @return self
     */
    public function rejectUnresolved(bool $on = true): self
    {
        $c = clone $this;
        $c->rejectUnresolved = $on;

        return $c;
    }

    /**
     * Set dependency handling in one flag: keep every package external, or bundle them all.
     *
     * @param 'external'|'bundle' $mode
     *
     * @return self
     */
    public function packages(string $mode): self
    {
        $c = clone $this;
        $c->packages = $mode;

        return $c;
    }

    /**
     * Add a per-extension loader override, given as ".ext:loader" (e.g. ".svg:text").
     *
     * @param string $extLoader
     *
     * @return self
     */
    public function loader(string $extLoader): self
    {
        $c = clone $this;
        $c->loader[] = $extLoader;

        return $c;
    }

    /**
     * Add a custom package.json export condition used during resolution.
     *
     * @param string $condition
     *
     * @return self
     */
    public function conditions(string $condition): self
    {
        $c = clone $this;
        $c->conditions[] = $condition;

        return $c;
    }

    /**
     * Set environment-variable inlining: 'inline', 'disable', or a prefix pattern like "PUBLIC_*".
     *
     * @param string $mode
     *
     * @return self
     */
    public function env(string $mode): self
    {
        $c = clone $this;
        $c->env = $mode;

        return $c;
    }

    /**
     * Write the module-graph markdown visualization to the given path.
     *
     * @param string $path
     *
     * @return self
     */
    public function metafileMd(string $path): self
    {
        $c = clone $this;
        $c->metafileMd = $path;

        return $c;
    }

    /**
     * Toggle keeping the terminal scrollback on watch-mode rebuilds.
     *
     * @param bool $on
     *
     * @return self
     */
    public function noClearScreen(bool $on = true): self
    {
        $c = clone $this;
        $c->noClearScreen = $on;

        return $c;
    }

    /**
     * Freeze the spec into an immutable BuildOptions value object.
     *
     * @return BuildOptions
     */
    public function toOptions(): BuildOptions
    {
        return new BuildOptions(
            outDir: $this->outDir,
            outFile: $this->outFile,
            target: $this->target,
            format: $this->format,
            minify: $this->minify,
            minifySyntax: $this->minifySyntax,
            minifyWhitespace: $this->minifyWhitespace,
            minifyIdentifiers: $this->minifyIdentifiers,
            splitting: $this->splitting,
            sourcemap: $this->sourcemap,
            hashedNames: $this->hashedNames,
            chunkNaming: $this->chunkNaming,
            assetNaming: $this->assetNaming,
            metafile: $this->metafile,
            define: $this->define,
            external: $this->external,
            banner: $this->banner,
            footer: $this->footer,
            drop: $this->drop,
            watch: $this->watch,
            root: $this->root,
            publicPath: $this->publicPath,
            entryNaming: $this->entryNaming,
            cssChunking: $this->cssChunking,
            keepNames: $this->keepNames,
            rejectUnresolved: $this->rejectUnresolved,
            packages: $this->packages,
            loader: $this->loader,
            conditions: $this->conditions,
            env: $this->env,
            metafileMd: $this->metafileMd,
            noClearScreen: $this->noClearScreen,
        );
    }
}
