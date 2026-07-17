<?php

declare(strict_types=1);

/**
 * Class discovery front door, combining Composer classmap and token-based scanning.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Attribute\Discovery;

final class ClassDiscovery
{
    /**
     * Create a discovery that prefers the classmap and falls back to token scanning.
     *
     * @param ?ComposerDiscovery $composerDiscovery
     * @param ?TokenDiscovery $tokenDiscovery
     */
    public function __construct(
        private readonly ?ComposerDiscovery $composerDiscovery = null,
        private readonly ?TokenDiscovery $tokenDiscovery = null,
    ) {}

    /**
     * Discover classes via the Composer classmap, falling back to token scanning.
     *
     * @param list<string> $directories
     * @param list<string> $namespaces
     * @param list<string> $excludePatterns
     * @param string $projectRoot
     *
     * @return list<class-string>
     */
    public function discover(
        array $directories,
        string $projectRoot = '',
        array $namespaces = [],
        array $excludePatterns = [],
    ): array {
        if ($this->composerDiscovery !== null && $projectRoot !== '') {
            $classes = $this->composerDiscovery->discover(
                projectRoot: $projectRoot,
                directories: $directories,
                namespaces: $namespaces,
                excludePatterns: $excludePatterns,
            );

            if ($classes !== []) {
                return $classes;
            }
        }

        if ($this->tokenDiscovery !== null) {
            return $this->tokenDiscovery->discover(
                directories: $directories,
                namespaces: $namespaces,
                excludePatterns: $excludePatterns,
            );
        }

        return [];
    }
}
