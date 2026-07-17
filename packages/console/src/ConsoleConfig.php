<?php

declare(strict_types=1);

/**
 * Console configuration: command discovery paths and cache location.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Console;

use PHPdot\Container\Attribute\Config;

#[Config('console')]
final readonly class ConsoleConfig
{
    /**
     * Create the console configuration.
     *
     * @param string $name Application name
     * @param string $version Application version
     * @param string $cachePath Path to command cache file (empty = no cache)
     */
    public function __construct(
        public string $name = 'PHPdot',
        public string $version = '1.0.0',
        public string $cachePath = '',
    ) {}
}
