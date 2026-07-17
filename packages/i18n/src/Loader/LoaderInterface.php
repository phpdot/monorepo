<?php

declare(strict_types=1);

/**
 * Source-agnostic translation loader.
 *
 * Implementations resolve a flat key => ICU template map for a given language
 * code. Keys are dot-separated (`messages.welcome`); values are ICU
 * MessageFormat templates. Bind one implementation as `LoaderInterface` in
 * the container, or compose several through `ChainLoader`.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\I18n\Loader;

interface LoaderInterface
{
    /**
     * Load all translations for a language.
     *
     * @param string $language
     *
     * @return array<string, string> Flat key => ICU template map (e.g. 'messages.welcome' => 'Welcome!')
     */
    public function loadAll(string $language): array;
}
