<?php

declare(strict_types=1);

/**
 * Reads Bun's `--metafile` JSON and resolves a source entrypoint to its hashed output URL.
 *
 * A single entrypoint can produce more than one output: `index.ts` that imports `app.css` emits both
 * a `.js` and a `.css` with the IDENTICAL `entryPoint`, differing only by extension. So outputs are
 * keyed by (entryPoint, extension) and disambiguated via {@see js()}/{@see css()}/{@see asset()}.
 * Shared chunks (no `entryPoint`) are never returned — the HTML only references entries.
 *
 * Coroutine-safe: the parsed map is cached per instance, no static state. Construct a new Manifest
 * to re-read after a rebuild.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Manifest;

final class Manifest
{
    /**
     * @var array<string, array<string, string>>|null entryPoint => [ ext => output-path ]
     */
    private null|array $map = null;

    /**
     * Point the manifest at a Bun metafile and the public URL prefix for its assets.
     *
     * @param string $metafilePath
     * @param string $publicPrefix
     */
    public function __construct(
        private readonly string $metafilePath,
        private readonly string $publicPrefix = '/build',
    ) {}

    /**
     * Distil a verbose Bun --metafile into a trimmed, deploy-safe manifest: `outputs` + `entryPoint`
     * only. It drops the `inputs` section, whose `imports[].path` entries are absolute build-machine
     * paths (a filesystem-layout leak). The result is relative-only — safe to commit, deploy, and
     * even web-serve — and is the file {@see Manifest} reads.
     *
     * Returns true when the manifest was written; false if the metafile was unreadable, malformed,
     * or the write failed (so a caller can surface a build that succeeded but produced no manifest).
     *
     * @param string $manifestPath
     * @param string $metafilePath
     *
     * @return bool
     */
    public static function compile(string $metafilePath, string $manifestPath): bool
    {
        $raw = @file_get_contents($metafilePath);
        if ($raw === false) {
            return false;
        }

        try {
            $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        $outputs = is_array($data) ? ($data['outputs'] ?? null) : null;

        $trimmed = [];
        if (is_array($outputs)) {
            foreach ($outputs as $out => $meta) {
                if (is_array($meta) && is_string($meta['entryPoint'] ?? null)) {
                    $trimmed[(string) $out] = ['entryPoint' => $meta['entryPoint']];
                }
            }
        }

        $dir = dirname($manifestPath);
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            return false;
        }

        return file_put_contents(
            $manifestPath,
            json_encode(['outputs' => $trimmed], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n",
        ) !== false;
    }

    /**
     * Every distinct entrypoint the build produced, in manifest order.
     *
     * @throws ManifestNotReadableException
     *
     * @return list<string>
     */
    public function entries(): array
    {
        return array_keys($this->load());
    }

    /**
     * The entrypoint when the build produced exactly one.
     *
     * Exists so nothing outside the build has to restate the entry name. The recipe that compiles
     * the assets already names it and the manifest already records it; a consumer repeating that
     * string is a second definition of a build fact, and renaming the entry then breaks serving
     * while the build still passes.
     *
     * @throws AmbiguousEntryException If the build produced no entrypoint, or more than one.
     * @throws ManifestNotReadableException
     *
     * @return string
     */
    public function sole(): string
    {
        $entries = $this->entries();

        return match (count($entries)) {
            1 => $entries[0],
            0 => throw AmbiguousEntryException::none($this->metafilePath),
            default => throw AmbiguousEntryException::several($entries),
        };
    }

    /**
     * Resolve a JS source entry to its hashed public build URL.
     *
     * @param ?string $sourceEntry Null uses {@see sole()} — the build's only entrypoint
     *
     * @return string
     */
    public function js(null|string $sourceEntry = null): string
    {
        return $this->resolve($sourceEntry ?? $this->sole(), 'js');
    }

    /**
     * Resolve a CSS source entry to its hashed public build URL.
     *
     * @param ?string $sourceEntry Null uses {@see sole()} — the build's only entrypoint
     *
     * @return string
     */
    public function css(null|string $sourceEntry = null): string
    {
        return $this->resolve($sourceEntry ?? $this->sole(), 'css');
    }

    /**
     * Resolve a source entry of the given extension to its public build URL.
     *
     * @param ?string $sourceEntry Null uses {@see sole()} — the build's only entrypoint
     * @param string $ext
     *
     * @return string
     */
    public function asset(null|string $sourceEntry, string $ext): string
    {
        return $this->resolve($sourceEntry ?? $this->sole(), $ext);
    }

    /**
     * Resolves a source entry and extension to its hashed public URL via the loaded metafile.
     *
     * @param string $sourceEntry
     * @param string $ext
     *
     * @throws ManifestEntryNotFoundException
     * @throws ManifestNotReadableException
     *
     * @return string
     */
    private function resolve(string $sourceEntry, string $ext): string
    {
        $map = $this->load();

        $byExt = $map[$sourceEntry] ?? throw new ManifestEntryNotFoundException($sourceEntry, array_keys($map));
        $out = $byExt[$ext] ?? throw new ManifestEntryNotFoundException(
            sprintf('%s (%s)', $sourceEntry, $ext),
            array_keys($byExt),
        );

        return rtrim($this->publicPrefix, '/') . '/' . self::relativeOutput($out);
    }

    /**
     * Bun's metafile keys are already relative to the output dir but carry one or more leading "./"
     * segments (e.g. "././app-hash.js" for a root entry, "./admin/panel-hash.js" for a nested one).
     * Strip those segments while preserving real subdirectories, so a nested entry resolves to a
     * nested URL instead of being flattened to its basename.
     *
     * @param string $outputPath
     *
     * @return string
     */
    private static function relativeOutput(string $outputPath): string
    {
        while (str_starts_with($outputPath, './')) {
            $outputPath = substr($outputPath, 2);
        }

        return $outputPath;
    }

    /**
     * Loads and caches the parsed build metafile as a source-entry to output map.
     *
     * @throws ManifestNotReadableException
     *
     * @return array<string, array<string, string>>
     */
    private function load(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }

        $raw = @file_get_contents($this->metafilePath);
        if ($raw === false) {
            throw new ManifestNotReadableException($this->metafilePath);
        }

        try {
            $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ManifestNotReadableException($this->metafilePath);
        }

        $outputs = is_array($data) ? ($data['outputs'] ?? null) : null;

        $map = [];
        if (is_array($outputs)) {
            foreach ($outputs as $outPath => $meta) {
                if (!is_array($meta) || !is_string($meta['entryPoint'] ?? null)) {
                    continue;
                }
                $path = (string) $outPath;
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $map[$meta['entryPoint']][$ext] = $path;
            }
        }

        return $this->map = $map;
    }
}
