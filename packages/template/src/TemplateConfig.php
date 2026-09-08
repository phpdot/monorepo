<?php

declare(strict_types=1);

/**
 * Configuration value object for the Twig engine: template paths, cache, and rendering flags.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Template;

use PHPdot\Container\Attribute\Config;

#[Config('template')]
final readonly class TemplateConfig
{
    /**
     * Immutable Twig configuration: template paths, cache location, and rendering flags.
     *
     * @param array<string, list<string>> $paths Namespace => directories ('__main__' is the default).
     * @param string|null $cache Absolute path to compiled-template cache. Null disables caching.
     * @param bool $debug Enable debug extension (dump()) and verbose errors.
     * @param bool $strictVariables Undefined variables throw instead of returning null.
     * @param string $charset Template charset.
     * @param bool $autoReload Recompile on source change (dev only).
     * @param string|false $autoescape Escaping strategy. 'html' or false.
     * @param bool $format Re-indent rendered browser HTML through ext-tidy. On by default;
     *                     requires ext-tidy, so set false where the extension is absent.
     * @param array<string, bool|int|string> $formatOptions Tidy options merged OVER the house
     *                                                      defaults — override any of them, or add
     *                                                      any other tidy option. `show-body-only`
     *                                                      is ignored: {@see HtmlFormatter} decides
     *                                                      it per input, because the wrong value
     *                                                      silently discards a document's head.
     */
    public function __construct(
        public array $paths = ['__main__' => []],
        public null|string $cache = null,
        public bool $debug = false,
        public bool $strictVariables = true,
        public string $charset = 'UTF-8',
        public bool $autoReload = false,
        public string|false $autoescape = 'html',
        public bool $format = true,
        public array $formatOptions = [],
    ) {}
}
