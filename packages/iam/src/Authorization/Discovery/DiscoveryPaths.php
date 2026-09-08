<?php

declare(strict_types=1);

/**
 * The directories iam:permission:sync scans — bound by the host application
 * (dot: Apps + System). A value object so the sync command can receive the
 * layout without knowing the host's path registry.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\Discovery;

final readonly class DiscoveryPaths
{
    /**
     * @param list<string> $directories Absolute directories to scan
     */
    public function __construct(
        public array $directories,
    ) {}
}
