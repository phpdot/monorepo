<?php

declare(strict_types=1);

/**
 * Loader that reads JSON files containing nested or flat translation objects.
 *
 * Scans `<path>/<language>/*.json` for every path in `config.paths`, with the
 * same key rules as `PhpArrayLoader`. Useful for sharing the same source files
 * with non-PHP tooling. Not auto-bound — register explicitly or compose via
 * `ChainLoader` when you want to mix it with `PhpArrayLoader`.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\I18n\Loader;

use PHPdot\I18n\I18nConfig;

final class JsonLoader implements LoaderInterface
{
    /**
     * A loader that reads translations from per-language JSON files.
     *
     * @param I18nConfig $config Provides the base paths the JSON files are read from
     */
    public function __construct(
        private readonly I18nConfig $config,
    ) {}

    /**
     * Load all translations for a language from JSON files.
     *
     * Unreadable or malformed files are skipped, matching PhpArrayLoader:
     * a half-written file mid-deploy costs its own keys, never the boot.
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

            $files = glob($directory . '/*.json');

            if ($files === false) {
                continue;
            }

            foreach ($files as $file) {
                $contents = file_get_contents($file);

                if ($contents === false) {
                    continue;
                }

                try {
                    $entries = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    continue;
                }

                if (!is_array($entries)) {
                    continue;
                }

                $translations = array_merge($translations, self::flatten($entries, basename($file, '.json')));
            }
        }

        ksort($translations);

        return $translations;
    }

    /**
     * Flatten one file's object onto dotted keys. A non-string leaf is dropped
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
