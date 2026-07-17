<?php

declare(strict_types=1);

/**
 * Immutable configuration for the Bun wrapper.
 *
 * The pinned version must never silently track "latest" — reproducible builds require a pin. Set it
 * (and the other values) in config/bun.php; phpdot/config hydrates this DTO, falling back to the
 * defaults below.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Config;

use PHPdot\Container\Attribute\Config;

#[Config('bun')]
final readonly class BunConfig
{
    /**
     * Holds the bun runtime configuration: pinned version, registry URL, runtime and working directories.
     *
     * @param string|null $workingDir Default working directory for the package-context commands
     *                                (install/remove/view/run/x) so package.json + node_modules land
     *                                there, not the project root. null = current dir. build()/watch()
     *                                are unaffected — they take project-relative paths and resolve
     *                                node_modules by the normal upward walk.
     * @param string $pinnedVersion
     * @param string $registryUrl
     * @param string $runtimeDir
     */
    public function __construct(
        public string $pinnedVersion = '1.3.14',
        public string $registryUrl = 'https://registry.npmjs.org',
        public string $runtimeDir = '.phpdot/runtime',
        public ?string $workingDir = null,
    ) {}
}
