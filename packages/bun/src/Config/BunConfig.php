<?php

declare(strict_types=1);

/**
 * Immutable configuration for the Bun asset pipeline.
 *
 * The pinned version must never silently track "latest" — reproducible builds require a pin.
 * Every directory is ABSOLUTE: a relative path would resolve against whatever directory the
 * command happens to run from, and the runtime would be re-downloaded per working directory.
 * The install hook fills the scaffolded empties with `dirname(__DIR__)` expressions, which are
 * absolute at load time and keep the config file portable.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Config;

use PHPdot\Bun\Exception\InvalidBunConfigException;
use PHPdot\Container\Attribute\Config;

#[Config('bun')]
final readonly class BunConfig
{
    /**
     * The JavaScript yard inside resources — derived, never configured: package.json,
     * node_modules, tsconfig, the runtime binary, and build intermediates all live here.
     */
    public readonly string $homeDir;

    /**
     * Holds the pipeline configuration: pin, registry, resources, output, and URL base.
     *
     * @param string $pinnedVersion The Bun version to install and execute — never "latest"
     * @param string $registryUrl The npm registry the binary and packages resolve from
     * @param string $resourcesDir Absolute directory of authored sources — the developer's
     *                             own files, nothing generated; home is its `.bun` subdirectory
     * @param string $outputDir Absolute directory the build writes to, including manifest.json
     * @param null|string $baseUrl URL prefix for manifest entries: null derives it from
     *                             outputDir, a path overrides it, a full https URL points at a CDN
     *
     * @throws InvalidBunConfigException When a directory key is empty or holds a relative path
     */
    public function __construct(
        public string $pinnedVersion = '1.4.0',
        public string $registryUrl = 'https://registry.npmjs.org',
        public string $resourcesDir = '',
        public string $outputDir = '',
        public null|string $baseUrl = null,
    ) {
        foreach (['resourcesDir' => $resourcesDir, 'outputDir' => $outputDir] as $key => $value) {
            if ($value === '') {
                throw InvalidBunConfigException::notSet($key);
            }

            if (!self::isAbsolute($value)) {
                throw InvalidBunConfigException::relativePath($key, $value);
            }
        }

        $this->homeDir = $resourcesDir . '/.bun';
    }

    /**
     * The URL prefix manifest entries resolve against: the configured baseUrl
     * verbatim, or — when null — derived from the output directory's name
     * (`public/build` → `/build`), matching where the files land under a
     * standard document root.
     *
     * @return string
     */
    public function assetPrefix(): string
    {
        return $this->baseUrl ?? ('/' . basename($this->outputDir));
    }

    /**
     * Whether the path is absolute on this platform.
     *
     * @param string $path The configured value
     *
     * @return bool
     */
    private static function isAbsolute(string $path): bool
    {
        if (DIRECTORY_SEPARATOR === '/') {
            return str_starts_with($path, '/');
        }

        return (bool) preg_match('#^[A-Za-z]:[/\\\\]#', $path);
    }
}
