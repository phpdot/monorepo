<?php

declare(strict_types=1);

/**
 * Default loader — reads PHP files returning nested or flat translation arrays.
 *
 * Scans `<path>/<language>/*.php` for every path in `config.paths`. Keys are
 * prefixed by the filename and nested arrays flatten onto that with dots, so
 * `messages.php` gives `messages.welcome`. A later path wins a duplicate key.
 * Files that don't return an array are skipped, to keep partial deploys safe.
 * Auto-bound to `LoaderInterface` via `#[Binds]`.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\I18n\Loader;

use PHPdot\Container\Attribute\Binds;
use PHPdot\Container\Attribute\Singleton;
use PHPdot\I18n\I18nConfig;

#[Singleton]
#[Binds(LoaderInterface::class)]
final class PhpArrayLoader implements LoaderInterface
{
    /**
     * A loader that reads translations from per-language PHP array files.
     *
     * @param I18nConfig $config Provides the base paths the PHP files are read from
     */
    public function __construct(
        private readonly I18nConfig $config,
    ) {}

    /**
     * Load all translations for a language from PHP array files.
     *
     * @return array<string, string> Flat key => ICU template map
     */
    public function loadAll(string $language): array
    {
        $translations = [];

        foreach ($this->config->paths as $path) {
            $directory = $path . '/' . $language;

            if (!is_dir($directory)) {
                continue;
            }

            $files = glob($directory . '/*.php');

            if ($files === false) {
                continue;
            }

            foreach ($files as $file) {
                $entries = require $file;

                if (!is_array($entries)) {
                    continue;
                }

                $translations = array_merge($translations, self::flatten($entries, basename($file, '.php')));
            }
        }

        ksort($translations);

        return $translations;
    }

    /**
     * Flatten one file's array onto dotted keys. A non-string leaf is dropped
     * rather than coerced, so the key resolves as missing and shows up.
     *
     * @param array<array-key, mixed> $entries
     *
     * @return array<string, string>
     */
    private static function flatten(array $entries, string $prefix): array
    {
        $flat = [];

        foreach ($entries as $key => $value) {
            $path = $prefix . '.' . $key;

            if (is_array($value)) {
                $flat = array_merge($flat, self::flatten($value, $path));

                continue;
            }

            if (is_string($value)) {
                $flat[$path] = $value;
            }
        }

        return $flat;
    }
}
