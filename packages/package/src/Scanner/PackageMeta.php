<?php

declare(strict_types=1);

/**
 * Per-package metadata extracted from installed.json.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package\Scanner;

final readonly class PackageMeta
{
    /**
     * Create the package metadata value object.
     *
     * @param string $name Composer package name
     * @param string $description Package description
     * @param string $url Package source URL
     * @param string $author Author formatted as "Name <email>"
     */
    public function __construct(
        public string $name,
        public string $description = '',
        public string $url = '',
        public string $author = '',
    ) {}
}
