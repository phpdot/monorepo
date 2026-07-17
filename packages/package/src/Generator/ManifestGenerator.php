<?php

declare(strict_types=1);

/**
 * Manifest Generator
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package\Generator;

use PHPdot\Package\Scanner\PackageMeta;
use PHPdot\Package\Scanner\ScannedClass;

final class ManifestGenerator
{
    /**
     * Generate the manifest file from the scan result.
     *
     * @param list<ScannedClass> $classes
     * @param array<string, PackageMeta> $packages
     * @param list<string> $ownedConfigs Owned config file names, relative to the config directory
     *
     * @return string
     */
    public function generate(
        array $classes,
        array $packages = [],
        array $ownedConfigs = [],
    ): string {
        $grouped = $this->groupByPackage($classes);
        $timestamp = date('c');

        $lines = [];
        $lines[] = "<?php\n";
        $lines[] = "\ndeclare(strict_types=1);\n";
        $lines[] = $this->generateHeader($timestamp);
        $lines[] = "\nreturn [\n";
        $lines[] = "\n    'generated_at' => '{$timestamp}',\n";
        $lines[] = "\n    'ownedConfigs' => " . $this->formatPathList($ownedConfigs, 8) . ",\n";
        $lines[] = "\n    'packages' => [\n";

        foreach ($grouped as $packageName => $group) {
            $meta = $packages[$packageName] ?? null;
            $lines[] = $this->generatePackageBlock($packageName, $group, $meta);
        }

        $lines[] = "\n    ],\n";
        $lines[] = "\n];\n";

        return implode('', $lines);
    }

    /**
     * Format a list of paths for the manifest.
     *
     * @param list<string> $paths
     * @param int $indentSpaces
     *
     * @return string
     */
    private function formatPathList(array $paths, int $indentSpaces): string
    {
        if ($paths === []) {
            return '[]';
        }

        $indent = str_repeat(' ', $indentSpaces);
        $close = str_repeat(' ', $indentSpaces - 4);

        $lines = "[\n";

        foreach ($paths as $path) {
            $lines .= $indent . "'" . addslashes($path) . "',\n";
        }

        $lines .= $close . ']';

        return $lines;
    }

    /**
     * Generate header.
     *
     * @param string $timestamp
     *
     * @return string
     */
    private function generateHeader(string $timestamp): string
    {
        return <<<PHP

            /**
             * PHPdot Package Manifest
             *
             * @generated   phpdot/package
             * @date        {$timestamp}
             * @see         https://github.com/phpdot/package
             *
             * Regenerated on every composer install/update/require/remove.
             * Do not edit — changes will be overwritten.
             */

            PHP;
    }

    /**
     * Render one package's block in the manifest.
     *
     * @param list<ScannedClass> $group
     * @param string $packageName
     * @param ?PackageMeta $meta
     *
     * @return string
     */
    private function generatePackageBlock(string $packageName, array $group, ?PackageMeta $meta): string
    {
        $lines = "\n        '{$packageName}' => [\n";

        $description = $meta !== null ? $meta->description : '';
        $url = $meta !== null ? $meta->url : '';
        $author = $meta !== null ? $meta->author : '';

        $lines .= "            'description' => " . $this->quote($description) . ",\n";
        $lines .= "            'url' => " . $this->quote($url) . ",\n";
        $lines .= "            'author' => " . $this->quote($author) . ",\n";

        $lines .= "            'services' => [\n";
        foreach ($group as $scanned) {
            if ($scanned->scope === null) {
                continue;
            }

            $escapedClass = $this->escapeBackslash($scanned->class);
            $lines .= "                '{$escapedClass}' => '{$scanned->scope->name}',\n";
        }
        $lines .= "            ],\n";

        $configs = [];
        foreach ($group as $scanned) {
            if ($scanned->configName !== null) {
                $configs[$scanned->class] = $scanned->configName;
            }
        }
        $lines .= "            'configs' => [\n";
        foreach ($configs as $class => $configName) {
            $escapedClass = $this->escapeBackslash($class);
            $lines .= "                '{$escapedClass}' => '{$configName}',\n";
        }
        $lines .= "            ],\n";

        $bindings = [];
        foreach ($group as $scanned) {
            foreach ($scanned->binds as $interface) {
                $bindings[$interface] = $scanned->class;
            }
        }
        $lines .= "            'bindings' => [\n";
        foreach ($bindings as $interface => $implementation) {
            $escapedIface = $this->escapeBackslash($interface);
            $escapedImpl = $this->escapeBackslash($implementation);
            $lines .= "                '{$escapedIface}' => '{$escapedImpl}',\n";
        }
        $lines .= "            ],\n";

        $lines .= "        ],\n";

        return $lines;
    }

    /**
     * Quote.
     *
     * @param string $value
     *
     * @return string
     */
    private function quote(string $value): string
    {
        return "'" . addslashes($value) . "'";
    }

    /**
     * Escape backslash.
     *
     * @param string $value
     *
     * @return string
     */
    private function escapeBackslash(string $value): string
    {
        return str_replace('\\', '\\\\', $value);
    }

    /**
     * Group scanned classes by their owning package.
     *
     * @param list<ScannedClass> $classes
     *
     * @return array<string, list<ScannedClass>>
     */
    private function groupByPackage(array $classes): array
    {
        $grouped = [];

        foreach ($classes as $scanned) {
            $grouped[$scanned->package][] = $scanned;
        }

        return $grouped;
    }
}
