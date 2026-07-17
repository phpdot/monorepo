<?php

declare(strict_types=1);

/**
 * InstallHandler
 *
 * Contract for #[InstallHook] classes. Runs CLI-only at composer time (no
 * container), after phpdot/package has generated the config files.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package\Contract;

interface InstallHandler
{
    /**
     * Perform install-time setup.
     *
     * @param string $projectRoot Absolute path to the project root
     * @param string $configDir Absolute path to the application config directory
     *
     * @return string|null An optional one-line message to log, or null for silent
     */
    public static function install(string $projectRoot, string $configDir): ?string;
}
