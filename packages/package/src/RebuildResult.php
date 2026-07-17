<?php

declare(strict_types=1);

/**
 * Rebuild Result
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package;

final readonly class RebuildResult
{
    /**
     * Create the rebuild result.
     *
     * @param int $packageCount Packages discovered
     * @param int $serviceCount Services registered
     * @param int $bindingCount Interface bindings declared via #[Binds]
     * @param int $configCount Config DTOs found
     * @param list<string> $generatedConfigs Paths of newly generated config files
     * @param list<string> $orphanedConfigs Paths of config files no longer backed by an installed package
     * @param list<string> $installMessages One-line messages returned by #[InstallHook] handlers
     */
    public function __construct(
        public int $packageCount,
        public int $serviceCount,
        public int $bindingCount,
        public int $configCount,
        public array $generatedConfigs,
        public array $orphanedConfigs = [],
        public array $installMessages = [],
    ) {}
}
