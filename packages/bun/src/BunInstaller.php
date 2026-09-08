<?php

declare(strict_types=1);

/**
 * BunInstaller
 *
 * Install-time hook (run by phpdot/package after config files are generated). Fills the
 * scaffolded empty directory keys in config/bun.php with `dirname(__DIR__)` expressions —
 * absolute at load time, portable across machines and relocations, so the runtime is
 * installed once and found from any working directory. Idempotent: a key that is already
 * set is never touched.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun;

use PHPdot\Package\Attribute\InstallHook;
use PHPdot\Package\Contract\InstallHandler;

#[InstallHook]
final class BunInstaller implements InstallHandler
{
    /**
     * The scaffold default and the portable-absolute expression it becomes, per key.
     *
     * @var array<string, string>
     */
    private const array FILLS = [
        'resourcesDir' => "'resources'",
        'outputDir' => "'public/build'",
    ];

    public static function install(string $projectRoot, string $configDir): null|string
    {
        $configFile = $configDir . '/bun.php';

        if (!is_file($configFile)) {
            return null;
        }

        $content = (string) file_get_contents($configFile);
        $filled = [];

        foreach (self::FILLS as $key => $path) {
            $needle = "'{$key}' => ''";

            if (!str_contains($content, $needle)) {
                continue;
            }

            $content = str_replace($needle, "'{$key}' => dirname(__DIR__) . '/' . {$path}", $content);
            $filled[] = $key;
        }

        if ($filled === []) {
            return null;
        }

        file_put_contents($configFile, $content);

        return 'phpdot/bun: set ' . implode(', ', $filled) . ' to portable absolute paths';
    }
}
