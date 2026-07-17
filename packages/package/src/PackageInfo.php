<?php

declare(strict_types=1);

/**
 * Package Info
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package;

final readonly class PackageInfo
{
    /**
     * Create the package info value object.
     *
     * @param string $name Composer package name
     * @param string $description Package description
     * @param string $url Package source URL
     * @param string $author Author formatted as "Name <email>"
     * @param array<string, string> $services class => scope string
     * @param array<string, string> $configs class => config name
     * @param array<string, string> $bindings interface => implementation
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $url,
        public string $author,
        public array $services,
        public array $configs,
        public array $bindings,
    ) {}
}
