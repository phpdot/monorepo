<?php

declare(strict_types=1);

/**
 * Result of a package scan containing scanned classes and package metadata.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package\Scanner;

final readonly class ScanResult
{
    /**
     * Create the scan result.
     *
     * @param list<ScannedClass> $classes All scanned classes
     * @param array<string, PackageMeta> $packages Package name => metadata
     */
    public function __construct(
        public array $classes,
        public array $packages,
    ) {}
}
