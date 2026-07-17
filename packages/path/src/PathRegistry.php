<?php

declare(strict_types=1);

/**
 * PathRegistry
 *
 * Resolves the `path` configuration section into absolute paths.
 *
 * `base` is a config key: when set (e.g. filled with the project root at install,
 * or by the developer), the config loader resolves `{path.base}/...` against it
 * and the values are already absolute. When empty, the loader strips `{path.base}`
 * to a root-relative path, so this prepends the auto-detected project root. Either
 * way every resolved path is absolute.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Path;

use PHPdot\Config\Configuration;
use PHPdot\Container\Attribute\Binds;
use PHPdot\Container\Attribute\Singleton;
use PHPdot\Path\Contract\PathRegistryInterface;
use PHPdot\Path\Exception\PathNotMapped;

#[Singleton]
#[Binds(PathRegistryInterface::class)]
final class PathRegistry implements PathRegistryInterface
{
    private readonly string $base;

    /**
     * @var array<string, string> Fully resolved absolute paths
     */
    private readonly array $resolved;

    /**
     * Resolve the full named-path map from the 'path' config section.
     *
     * @param Configuration $config Provides the 'path' section (base, config dir, vendor dir)
     */
    public function __construct(Configuration $config)
    {
        $section = $config->section('path');
        $configBase = is_string($section['base'] ?? null) ? $section['base'] : '';

        $prefilled = $configBase !== '';

        $this->base = $prefilled
            ? ProjectRoot::fromPath($configBase)->path
            : ProjectRoot::discover()->path;

        $this->resolved = $this->build($this->base, $section, $prefilled);
    }

    public function base(): string
    {
        return $this->base;
    }

    public function config(): string
    {
        return $this->get('config');
    }

    public function vendor(): string
    {
        return $this->get('vendor');
    }

    public function public(): string
    {
        return $this->get('public');
    }

    public function protected(): string
    {
        return $this->get('protected');
    }

    public function get(string $name): string
    {
        return $this->resolved[$name]
            ?? throw PathNotMapped::name($name, array_keys($this->resolved));
    }

    public function has(string $name): bool
    {
        return isset($this->resolved[$name]);
    }

    /**
     * Resolve every named path in the section against the base directory.
     *
     * @param string $base Absolute project-root base directory
     * @param array<string, mixed> $section The 'path' config section
     * @param bool $prefilled Whether the base came pre-filled from config (values are already absolute)
     *
     * @return array<string, string>
     */
    private function build(string $base, array $section, bool $prefilled): array
    {
        $dirs = $this->composerDirs($base);

        $resolved = [
            'base' => $this->normalize($base),
            'config' => $dirs['config'],
            'vendor' => $dirs['vendor'],
        ];

        foreach ($section as $name => $value) {
            if ($name === 'base' || $name === 'config' || $name === 'vendor' || !is_string($value)) {
                continue;
            }

            $resolved[$name] = $this->absolute($base, $value, $prefilled);
        }

        return $resolved;
    }

    /**
     * Resolve the config and vendor directories from the project composer.json:
     * `extra.phpdot.config-dir` (default `config`) and `config.vendor-dir`
     * (default `vendor`), both relative to the project root.
     *
     * @param string $base
     *
     * @return array{config: string, vendor: string}
     */
    private function composerDirs(string $base): array
    {
        $configDir = 'config';
        $vendorDir = 'vendor';
        $composerJson = $base . '/composer.json';

        if (is_file($composerJson)) {
            $data = json_decode((string) file_get_contents($composerJson), true);

            if (is_array($data)) {
                $config = $data['config'] ?? null;
                $vendor = is_array($config) ? ($config['vendor-dir'] ?? null) : null;

                if (is_string($vendor) && $vendor !== '') {
                    $vendorDir = $vendor;
                }

                $extra = $data['extra'] ?? null;
                $phpdot = is_array($extra) ? ($extra['phpdot'] ?? null) : null;
                $dir = is_array($phpdot) ? ($phpdot['config-dir'] ?? null) : null;

                if (is_string($dir) && $dir !== '') {
                    $configDir = $dir;
                }
            }
        }

        return [
            'config' => $this->normalize($base . '/' . trim($configDir, '/')),
            'vendor' => $this->normalize($base . '/' . trim($vendorDir, '/')),
        ];
    }

    /**
     * Resolve a configured value to an absolute path (base-relative unless pre-filled).
     *
     * @param string $base Absolute base directory
     * @param string $value The configured path value
     * @param bool $prefilled Whether $value is already absolute
     *
     * @return string
     */
    private function absolute(string $base, string $value, bool $prefilled): string
    {
        if ($prefilled) {
            return $this->normalize($value);
        }

        $relative = trim(str_replace('\\', '/', $value), '/');

        return $this->normalize($relative === '' ? $base : $base . '/' . $relative);
    }

    /**
     * Normalize slashes and strip trailing separators to the platform's directory separator.
     *
     * @param string $path The path to normalize
     *
     * @return string
     */
    private function normalize(string $path): string
    {
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $trimmed = rtrim($normalized, DIRECTORY_SEPARATOR);

        return $trimmed === '' ? DIRECTORY_SEPARATOR : $trimmed;
    }
}
