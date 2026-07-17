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
    private ?string $outDir = null;
    private ?string $outFile = null;
    private ?string $target = null;
    private ?string $format = null;
    private bool $minify = false;
    private bool $minifySyntax = false;
    private bool $minifyWhitespace = false;
    private bool $minifyIdentifiers = false;
    private bool $splitting = false;
    private ?string $sourcemap = null;
    private bool $hashedNames = false;
    private ?string $chunkNaming = null;
    private ?string $assetNaming = null;
    private ?string $metafile = null;
    /**
     * @var list<string>
     */
    private array $define = [];
    /**
     * @var list<string>
     */
    private array $external = [];
    private ?string $banner = null;
    private ?string $footer = null;
    /**
     * @var list<string>
     */
    private array $drop = [];
    private bool $watch = false;

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
        );
    }
}
