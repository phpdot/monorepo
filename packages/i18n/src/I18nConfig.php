<?php

declare(strict_types=1);

/**
 * Configuration DTO for the i18n system.
 *
 * Hydrated by `phpdot/config` from the `i18n` section. Holds the default
 * language, the list of supported codes, the base path that loaders resolve
 * against, and the PSR-16 cache TTL applied to compiled translation arrays.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\I18n;

use PHPdot\Container\Attribute\Config;

#[Config('i18n')]
final readonly class I18nConfig
{
    /**
     * Immutable i18n settings: default/supported languages, translation path, and cache TTL.
     *
     * @param string $default Default language code
     * @param list<string> $supported Supported language codes
     * @param string $path Base path to translation files
     * @param int $ttl Cache TTL in seconds
     */
    public function __construct(
        public string $default = 'en',
        public array $supported = ['en'],
        public string $path = '',
        public int $ttl = 3600,
    ) {}
}
