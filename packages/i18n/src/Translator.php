<?php

declare(strict_types=1);

/**
 * Translator with ICU MessageFormat support.
 *
 * Resolves dot-separated keys against a `LoaderInterface` and renders
 * templates through `ext-intl`. Holds a mutable active locale, an in-memory
 * cache of loaded translations, and delegates persistence across instances
 * to the injected PSR-16 cache. Falls back to the default language when a
 * key is missing in the active language; returns `[key]` and tracks the
 * miss when not found in either.
 *
 * The `#[Scoped]` attribute is a hint to `phpdot/container` so each
 * execution unit (request, coroutine, etc.) gets its own instance — the
 * class itself is runtime-agnostic and works standalone with any PSR-16
 * cache and a manual `new Translator(...)`.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\I18n;

use PHPdot\Container\Attribute\Scoped;
use PHPdot\Contracts\I18n\MessageTranslatorInterface;
use PHPdot\I18n\Loader\LoaderInterface;
use Psr\SimpleCache\CacheInterface;

#[Scoped]
final class Translator implements MessageTranslatorInterface
{
    private string $locale;
    private string $language;
    private string $region;

    /**
     * @var array<string, array<string, string>> language => translations (in-memory cache)
     */
    private array $translations = [];

    /**
     * @var array<string, list<string>> language => [missing keys]
     */
    private array $missing = [];

    /**
     * Wire the translator to its loader, PSR-16 cache, and configuration.
     *
     * @param LoaderInterface $loader Loads the flat key => ICU template map per language
     * @param CacheInterface $cache PSR-16 cache for compiled translation maps
     * @param I18nConfig $config Supported languages, path, and cache TTL
     */
    public function __construct(
        private readonly LoaderInterface $loader,
        private readonly CacheInterface $cache,
        private readonly I18nConfig $config = new I18nConfig(),
    ) {
        $this->locale = $config->default;
        $this->language = $config->default;
        $this->region = '';
    }

    /**
     * Set the active locale.
     *
     * Parses the locale string (e.g. `en_US`) into language and region.
     * Falls back to the default if the language is not supported.
     *
     * @param string $locale
     *
     * @return void
     */
    public function setLocale(string $locale): void
    {
        $parts = explode('_', $locale);
        $language = $parts[0];
        $region = $parts[1] ?? '';

        if (!$this->isSupported($language)) {
            $this->locale = $this->config->default;
            $this->language = $this->config->default;
            $this->region = '';

            return;
        }

        $this->locale = $locale;
        $this->language = $language;
        $this->region = $region;
    }

    /**
     * Translate a key with optional ICU MessageFormat parameters.
     *
     * Returns `[key]` if the key is not found in any language.
     *
     * @param string $key Translation key (e.g. 'messages.welcome')
     * @param array<string, mixed> $params ICU MessageFormat parameters
     */
    public function translate(string $key, array $params = []): string
    {
        $template = $this->getTemplate($key);

        if ($template === null) {
            if (!isset($this->missing[$this->language])) {
                $this->missing[$this->language] = [];
            }

            if (!in_array($key, $this->missing[$this->language], true)) {
                $this->missing[$this->language][] = $key;
            }

            return "[{$key}]";
        }

        $params = array_merge($params, [
            '_locale_' => $this->locale,
            '_region_' => $this->region,
            '_lang_' => $this->language,
        ]);

        try {
            $formatter = new \MessageFormatter($this->locale, $template);
        } catch (\IntlException) {
            return $template;
        }

        if ($formatter->getErrorCode() !== 0) {
            return $template;
        }

        $result = $formatter->format($params);

        if ($result === false) {
            return $template;
        }

        return $result;
    }

    /**
     * Return translations matching the given patterns.
     *
     * Loads current language translations merged on top of default language translations.
     *
     * Pattern syntax (dot-separated segments):
     * - `js.buttons`     — exact match + all children (`js.buttons`, `js.buttons.save`, ...)
     * - `js.buttons.*`   — direct children only (`js.buttons.save`, not `js.buttons.save.label`)
     * - `js.*.save`      — wildcard segment (`js.buttons.save`, `js.forms.save`)
     * - `js.**`          — all descendants (`js.buttons`, `js.buttons.save`, `js.errors.required`)
     * - `**`             — all translations
     *
     * @param list<string> $patterns Key patterns to filter by
     *
     * @return array<string, string> Filtered translations
     */
    public function exposed(array $patterns): array
    {
        if ($patterns === []) {
            return [];
        }

        $current = $this->loadTranslations($this->language);

        if ($this->language !== $this->config->default) {
            $defaults = $this->loadTranslations($this->config->default);
            $current = array_merge($defaults, $current);
        }

        $filtered = [];

        foreach ($current as $key => $value) {
            foreach ($patterns as $pattern) {
                if ($this->matchPattern($key, $pattern)) {
                    $filtered[$key] = $value;

                    break;
                }
            }
        }

        return $filtered;
    }

    /**
     * Match a translation key against a pattern.
     *
     * Supports:
     * - Exact match or prefix match (no wildcards): `js.buttons` matches `js.buttons` and `js.buttons.*`
     * - `*` matches exactly one segment
     * - `**` matches one or more segments (recursive)
     *
     * @param string $key
     * @param string $pattern
     *
     * @return bool
     */
    private function matchPattern(string $key, string $pattern): bool
    {
        if (!str_contains($pattern, '*')) {
            return $key === $pattern || str_starts_with($key, $pattern . '.');
        }

        if ($pattern === '**') {
            return true;
        }

        $patternSegments = explode('.', $pattern);
        $keySegments = explode('.', $key);

        return $this->matchSegments($keySegments, 0, $patternSegments, 0);
    }

    /**
     * Recursively match key segments against pattern segments.
     *
     * @param list<string> $key
     * @param list<string> $pattern
     * @param int $ki
     * @param int $pi
     *
     * @return bool
     */
    private function matchSegments(array $key, int $ki, array $pattern, int $pi): bool
    {
        $keyLen = count($key);
        $patLen = count($pattern);

        while ($pi < $patLen) {
            $seg = $pattern[$pi];

            if ($seg === '**') {
                if ($pi === $patLen - 1) {
                    return $ki < $keyLen;
                }

                for ($i = $ki; $i < $keyLen; $i++) {
                    if ($this->matchSegments($key, $i, $pattern, $pi + 1)) {
                        return true;
                    }
                }

                return false;
            }

            if ($ki >= $keyLen) {
                return false;
            }

            if ($seg !== '*' && $seg !== $key[$ki]) {
                return false;
            }

            $ki++;
            $pi++;
        }

        return $ki === $keyLen;
    }

    /**
     * Get all tracked missing translation keys grouped by language.
     *
     * @return array<string, list<string>>
     */
    public function getMissing(): array
    {
        return $this->missing;
    }

    /**
     * Clear cached translations.
     *
     * @param string|null $language Specific language to clear, or null for all supported languages
     *
     * @return void
     */
    public function clearCache(?string $language = null): void
    {
        if ($language !== null) {
            $this->cache->delete('i18n.' . $language);
            unset($this->translations[$language]);

            return;
        }

        foreach ($this->config->supported as $lang) {
            $this->cache->delete('i18n.' . $lang);
        }

        $this->translations = [];
    }

    /**
     * Get the current full locale string (e.g. `en_US`).
     *
     * @return string
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Get the current language code (e.g. `en`).
     *
     * @return string
     */
    public function getLanguage(): string
    {
        return $this->language;
    }

    /**
     * Get the current region code (e.g. `US`), or empty string if none.
     *
     * @return string
     */
    public function getRegion(): string
    {
        return $this->region;
    }

    /**
     * Get the default language code.
     *
     * @return string
     */
    public function getDefault(): string
    {
        return $this->config->default;
    }

    /**
     * Get all supported language codes.
     *
     * @return list<string>
     */
    public function getSupported(): array
    {
        return $this->config->supported;
    }

    /**
     * Check whether a language code is supported.
     *
     * @param string $language
     *
     * @return bool
     */
    public function isSupported(string $language): bool
    {
        return in_array($language, $this->config->supported, true);
    }

    /**
     * Look up a translation template by key, falling back to the default language.
     *
     * @param string $key
     *
     * @return ?string
     */
    private function getTemplate(string $key): ?string
    {
        $translations = $this->loadTranslations($this->language);

        if (isset($translations[$key])) {
            return $translations[$key];
        }

        if ($this->language !== $this->config->default) {
            $defaults = $this->loadTranslations($this->config->default);

            if (isset($defaults[$key])) {
                return $defaults[$key];
            }
        }

        return null;
    }

    /**
     * Load translations for a language with in-memory and PSR-16 caching.
     *
     * @param string $language
     *
     * @return array<string, string>
     */
    private function loadTranslations(string $language): array
    {
        if (isset($this->translations[$language])) {
            return $this->translations[$language];
        }

        $cacheKey = 'i18n.' . $language;

        /**
         * @var array<string, string>|null $cached
         */
        $cached = $this->cache->get($cacheKey);

        if (is_array($cached)) {
            $this->translations[$language] = $cached;

            return $cached;
        }

        $loaded = $this->loader->loadAll($language);
        $this->cache->set($cacheKey, $loaded, $this->config->ttl);
        $this->translations[$language] = $loaded;

        return $loaded;
    }
}
