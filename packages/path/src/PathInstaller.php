<?php

declare(strict_types=1);

/**
 * PathInstaller
 *
 * Install-time hook (discovered and run by phpdot/package after config files are
 * generated). Fills an empty `base` in config/path.php with a portable
 * `dirname(__DIR__, N)` expression resolving the project root, so `{path.base}`
 * carries a real absolute value into every config section — phpdot/config
 * substitutes it before the registry exists, and an empty base reaches sibling
 * sections as '' (root-anchored paths). Idempotent — it never overwrites a base
 * that is already set, and the expression it writes no longer matches the empty
 * literal it looks for.
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
    public static function install(string $projectRoot, string $configDir): null|string
    {
        $pathFile = $configDir . '/path.php';

        if (!is_file($pathFile)) {
            return null;
        }

        $count = 0;
        $updated = str_replace(
            "'base' => ''",
            "'base' => " . self::baseExpression($projectRoot, $configDir),
            (string) file_get_contents($pathFile),
            $count,
        );

        if ($count < 1) {
            return null;
        }

        file_put_contents($pathFile, $updated);

        return "phpdot/path: set base to {$projectRoot}";
    }

    /**
     * The expression written into an empty base: a dirname() walk from the
     * config file up to the project root. The value is present the moment the
     * config loads, in every section, and it moves with the project — unlike a
     * frozen absolute literal. A config directory outside the project root
     * cannot be walked and falls back to the literal.
     *
     * @param string $projectRoot Absolute path to the project root
     * @param string $configDir Absolute path to the config directory
     *
     * @return string A PHP expression evaluating to the project root
     */
    private static function baseExpression(string $projectRoot, string $configDir): string
    {
        $root = rtrim(str_replace('\\', '/', $projectRoot), '/');
        $dir = rtrim(str_replace('\\', '/', $configDir), '/');

        if ($root === '' || !str_starts_with($dir, $root . '/')) {
            return var_export($projectRoot, true);
        }

        $relative = substr($dir, strlen($root) + 1);

        return 'dirname(__DIR__, ' . (substr_count($relative, '/') + 1) . ')';
    }
}
