<?php

declare(strict_types=1);

/**
 * Default loader — reads PHP files returning `array<string, string>`.
 *
 * Scans `<config.path>/<language>/*.php`. Each file's keys are prefixed by
 * the filename without extension, so `messages.php` returning `['welcome' =>
 * 'Hi {name}']` becomes `messages.welcome`. Files that don't return an array
 * are silently skipped to keep partial deploys safe. Auto-bound to
 * `LoaderInterface` via `#[Binds]`.
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
     * @param I18nConfig $config Provides the base path the PHP files are read from
     */
    public function __construct(
        private readonly I18nConfig $config,
    ) {}

    /**
     * Load all translations for a language from PHP array files.
     *
     * Scans `$basePath/$language/*.php`. Each file must return `array<string, string>`.
     * Keys are prefixed by filename without extension (e.g. `messages.welcome`).
     *
     * @return array<string, string> Flat key => ICU template map
     */
    public function loadAll(string $language): array
    {
        $directory = $this->config->path . '/' . $language;

        if (!is_dir($directory)) {
            return [];
        }

        $translations = [];
        $files = glob($directory . '/*.php');

        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            $prefix = basename($file, '.php');
            $entries = require $file;

            if (!is_array($entries)) {
                continue;
            }

            /**
             * @var array<string, string> $entries
             */
            foreach ($entries as $key => $value) {
                $translations[$prefix . '.' . $key] = $value;
            }
        }

        ksort($translations);

        return $translations;
    }
}
