<?php

declare(strict_types=1);

/**
 * PathsConfig
 *
 * The constructor signature literally becomes the generated config/path.php
 * (scaffolded once by phpdot/package, hydrated by phpdot/config).
 *
 * Values use `{path.base}` for the project root and `{path.<name>}` for another
 * named path. `base` is editable and empty by default: when empty, {@see
 * PathRegistry} auto-detects the project root and prepends it (the portable
 * default); set it to an absolute path — e.g. filled with the root at install —
 * to pin it. Either way the resolved paths are absolute.
 *
 * `config` and `vendor` are NOT listed here: they are read from composer.json
 * (`extra.phpdot.config-dir` and `config.vendor-dir`), the single source of
 * truth, so they can never drift. Defaults are generic; an application remaps
 * them — and adds more named paths as top-level keys — by editing the file.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Path;

use PHPdot\Container\Attribute\Config;

#[Config('path')]
final readonly class PathsConfig
{
    /**
     * The config-backed named-path defaults, overridable in the 'path' config section.
     *
     * @param string $base Project root; empty to auto-detect, or an absolute path.
     * @param string $public Web server document root (publicly served).
     * @param string $protected Non-public application directory (where code lives).
     */
    public function __construct(
        public string $base = '',
        public string $public = '{path.base}/public',
        public string $protected = '{path.base}/protected',
    ) {}
}
