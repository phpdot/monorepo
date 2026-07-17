<?php

declare(strict_types=1);

/**
 * PathInstaller
 *
 * Install-time hook (discovered and run by phpdot/package after config files are
 * generated). Fills an empty `base` in config/path.php with the absolute project
 * root, so all `{path.base}/…` values resolve to absolute paths. Idempotent — it
 * never overwrites a base that is already set.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Path;

use PHPdot\Package\Attribute\InstallHook;
use PHPdot\Package\Contract\InstallHandler;

#[InstallHook]
final class PathInstaller implements InstallHandler
{
    public static function install(string $projectRoot, string $configDir): ?string
    {
        $pathFile = $configDir . '/path.php';

        if (!is_file($pathFile)) {
            return null;
        }

        $count = 0;
        $updated = str_replace(
            "'base' => ''",
            "'base' => " . var_export($projectRoot, true),
            (string) file_get_contents($pathFile),
            $count,
        );

        if ($count < 1) {
            return null;
        }

        file_put_contents($pathFile, $updated);

        return "phpdot/path: set base to {$projectRoot}";
    }
}
