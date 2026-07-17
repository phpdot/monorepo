<?php

declare(strict_types=1);

/**
 * Loader that composes multiple loaders into one.
 *
 * Calls `loadAll()` on each wrapped loader in order and merges the results.
 * Later loaders overwrite earlier ones for duplicate keys, so put the most
 * specific source last (e.g. project overrides after vendor catalogs).
 * Not auto-bound — register manually with the desired loader list.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\I18n\Loader;

final class ChainLoader implements LoaderInterface
{
    /**
     * Combine several loaders into one, with later loaders overriding earlier keys.
     *
     * @param list<LoaderInterface> $loaders Ordered list of loaders; last wins for duplicate keys
     */
    public function __construct(
        private readonly array $loaders,
    ) {}

    /**
     * Load all translations by merging results from every loader.
     *
     * Later loaders overwrite earlier ones for the same key.
     *
     * @return array<string, string> Flat key => ICU template map
     */
    public function loadAll(string $language): array
    {
        $translations = [];

        foreach ($this->loaders as $loader) {
            $translations = array_merge($translations, $loader->loadAll($language));
        }

        return $translations;
    }
}
