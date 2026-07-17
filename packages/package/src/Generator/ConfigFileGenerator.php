<?php

declare(strict_types=1);

/**
 * Config File Generator
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package\Generator;

use PHPdot\Package\Scanner\PackageMeta;
use PHPdot\Package\Scanner\ScannedClass;
use ReflectionClass;

final class ConfigFileGenerator
{
    /**
     * Generate config stub files for the discovered #[Config] classes.
     *
     * @param list<ScannedClass> $classes All scanned classes
     * @param array<string, PackageMeta> $packages Package metadata
     * @param string $configPath Absolute path to config directory
     * @param list<string> $environments Environment names for override blocks
     *
     * @return list<string> Generated file paths
     */
    public function generate(
        array $classes,
        array $packages,
        string $configPath,
        array $environments = ['development', 'production', 'staging'],
    ): array {
        $generated = [];

        foreach ($classes as $scanned) {
            if ($scanned->configName === null) {
                continue;
            }

            $filePath = $this->filePath($configPath, $scanned->configName);

            if (is_file($filePath)) {
                continue;
            }

            $directory = dirname($filePath);

            if (!is_dir($directory)) {
                mkdir($directory, 0o755, true);
            }

            $meta = $packages[$scanned->package] ?? new PackageMeta(name: $scanned->package);
            $content = $this->generateFile($scanned, $meta, $environments);
            file_put_contents($filePath, $content);
            $generated[] = $filePath;
        }

        return $generated;
    }

    /**
     * Compute every absolute path this generator owns for the given scan,
     * regardless of whether each file already exists. Used by PackageManager
     * to record owned files in the manifest and to detect orphans on the
     * next rebuild.
     *
     * @param list<ScannedClass> $classes
     * @param string $configPath
     *
     * @return list<string>
     */
    public function ownedPaths(array $classes, string $configPath): array
    {
        $paths = [];

        foreach ($classes as $scanned) {
            if ($scanned->configName === null) {
                continue;
            }

            $paths[] = $this->filePath($configPath, $scanned->configName);
        }

        sort($paths);

        return $paths;
    }

    /**
     * A config key maps to its file the same way the loader maps files to
     * sections: dots are directory separators, so 'server.http' lives at
     * config/server/http.php.
     *
     * @param string $configName
     * @param string $configPath
     *
     * @return string
     */
    private function filePath(string $configPath, string $configName): string
    {
        return rtrim($configPath, '/') . '/' . str_replace('.', '/', $configName) . '.php';
    }

    /**
     * Render a config file's default content for one scanned class without
     * writing anything to disk. Powers `package:config`, which shows a
     * package's pristine defaults without touching the developer's own file.
     *
     * @param list<string> $environments Environment names for override blocks
     * @param ScannedClass $scanned
     * @param PackageMeta $meta
     *
     * @return string
     */
    public function render(ScannedClass $scanned, PackageMeta $meta, array $environments = ['development', 'production', 'staging']): string
    {
        return $this->generateFile($scanned, $meta, $environments);
    }

    /**
     * Write one config stub file.
     *
     * @param list<string> $environments
     * @param ScannedClass $scanned
     * @param PackageMeta $meta
     *
     * @return string
     */
    private function generateFile(ScannedClass $scanned, PackageMeta $meta, array $environments): string
    {
        $lines = [];
        $lines[] = "<?php\n";
        $lines[] = "\ndeclare(strict_types=1);\n";
        $lines[] = $this->generateHeader($scanned, $meta);
        $lines[] = "\nreturn [\n";

        $ref = new ReflectionClass($scanned->class);
        $constructor = $ref->getConstructor();

        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $param) {
                $name = $param->getName();

                $description = $scanned->paramDescriptions[$name]
                    ?? $this->humanize($name);

                $default = $param->isDefaultValueAvailable()
                    ? $this->formatDefault($param->getDefaultValue())
                    : "''";

                $lines[] = "    /**\n";
                $lines[] = "     * {$description}\n";
                $lines[] = "     */\n";
                $lines[] = "    '{$name}' => {$default},\n";
            }
        }

        if ($environments !== []) {
            $lines[] = "\n    /**\n";
            $lines[] = "     * Environment overrides\n";
            $lines[] = "     *\n";
            $lines[] = "     * Values are merged on top of defaults based on the active environment.\n";
            $lines[] = "     * Handled automatically by phpdot/config.\n";
            $lines[] = "     */\n";

            foreach ($environments as $env) {
                $lines[] = "    '{$env}' => [\n";
                $lines[] = "    ],\n";
            }
        }

        $lines[] = "];\n";

        return implode('', $lines);
    }

    /**
     * Generate header.
     *
     * @param ScannedClass $scanned
     * @param PackageMeta $meta
     *
     * @return string
     */
    private function generateHeader(ScannedClass $scanned, PackageMeta $meta): string
    {
        $lines = [];
        $lines[] = "\n/**";

        $lines[] = "\n * {$scanned->package}";

        if ($meta->description !== '') {
            $lines[] = "\n * {$meta->description}";
        }

        $lines[] = "\n *";
        $lines[] = "\n * @package     {$scanned->package}";

        if ($meta->url !== '') {
            $lines[] = "\n * @see         {$meta->url}";
        }

        $lines[] = "\n * @see         phpdot/config";
        $lines[] = "\n * @generated   phpdot/package";
        $lines[] = "\n *";
        $lines[] = "\n * This is your file — modify it freely, we won't touch it.";
        $lines[] = "\n *";
        $lines[] = "\n * Note: `composer remove {$scanned->package}` does NOT delete this file.";
        $lines[] = "\n * phpdot/package will list it as orphaned on the next rebuild —";
        $lines[] = "\n * delete it manually to clean up.";
        $lines[] = "\n *";
        $lines[] = "\n * Commands:";
        $lines[] = "\n *   php dot package:config {$scanned->package}    Show original defaults";
        $lines[] = "\n *   php dot package:show {$scanned->package}      Package info: services, configs, bindings";
        $lines[] = "\n */\n";

        return implode('', $lines);
    }

    /**
     * Humanize.
     *
     * @param string $name
     *
     * @return string
     */
    private function humanize(string $name): string
    {
        $words = str_replace('_', ' ', $name);

        return ucfirst($words);
    }

    /**
     * Format default.
     *
     * @param mixed $value
     * @param int $indent
     *
     * @return string
     */
    private function formatDefault(mixed $value, int $indent = 1): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return "'" . addslashes($value) . "'";
        }

        if (is_array($value)) {
            return $this->formatArray($value, $indent);
        }

        if (is_object($value)) {
            return $this->formatNestedDto($value, $indent);
        }

        return "''";
    }

    /**
     * Recursively scaffold a nested DTO instance as a multi-line array.
     * Reads each public, initialised property and emits its current value.
     *
     * @param object $instance
     * @param int $indent
     *
     * @return string
     */
    private function formatNestedDto(object $instance, int $indent): string
    {
        $reflection = new ReflectionClass($instance);
        $parts = [];

        $inner = str_repeat('    ', $indent + 1);
        $close = str_repeat('    ', $indent);

        foreach ($reflection->getProperties() as $prop) {
            if (!$prop->isPublic() || !$prop->isInitialized($instance)) {
                continue;
            }

            $name = $prop->getName();
            $val = $prop->getValue($instance);
            $parts[] = $inner . "'{$name}' => " . $this->formatDefault($val, $indent + 1);
        }

        if ($parts === []) {
            return '[]';
        }

        return "[\n" . implode(",\n", $parts) . ",\n{$close}]";
    }

    /**
     * Render an array default. A list of scalars stays inline; a keyed array,
     * or one holding nested arrays or objects, is expanded one entry per line
     * and indented to match its depth.
     *
     * @param array<mixed> $value
     * @param int $indent
     *
     * @return string
     */
    private function formatArray(array $value, int $indent = 1): string
    {
        if ($value === []) {
            return '[]';
        }

        if (array_is_list($value) && $this->isScalarList($value)) {
            $items = array_map(fn(mixed $v): string => $this->formatDefault($v), $value);

            return '[' . implode(', ', $items) . ']';
        }

        $inner = str_repeat('    ', $indent + 1);
        $close = str_repeat('    ', $indent);
        $isList = array_is_list($value);
        $parts = [];

        foreach ($value as $k => $v) {
            $rendered = $this->formatDefault($v, $indent + 1);
            $parts[] = $isList
                ? $inner . $rendered
                : $inner . "'" . addslashes((string) $k) . "' => " . $rendered;
        }

        return "[\n" . implode(",\n", $parts) . ",\n{$close}]";
    }

    /**
     * Determine whether every element of a list is a scalar (or null), so it
     * can be rendered inline rather than expanded across multiple lines.
     *
     * @param array<mixed> $value
     *
     * @return bool
     */
    private function isScalarList(array $value): bool
    {
        foreach ($value as $item) {
            if (!is_scalar($item) && $item !== null) {
                return false;
            }
        }

        return true;
    }
}
