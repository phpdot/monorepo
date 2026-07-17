<?php

declare(strict_types=1);

/**
 * Scanned Class
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package\Scanner;

use PHPdot\Container\Scope;

final readonly class ScannedClass
{
    /**
     * Create the scanned-class value object.
     *
     * @param class-string $class Fully qualified class name
     * @param Scope|null $scope Container scope, or null for a non-service (e.g. install-hook-only) class
     * @param array<string, class-string> $params Required constructor params as name => resolvable type FQCN
     * @param list<class-string> $binds Interface FQCNs from #[Binds]
     * @param string|null $configName Config file name from #[Config], or null
     * @param string $package Composer package name
     * @param array<string, string> $paramDescriptions Parameter name => PHPDoc description
     * @param bool $installHook Whether the class is an #[InstallHook] handler
     */
    public function __construct(
        public string $class,
        public ?Scope $scope,
        public array $params,
        public array $binds,
        public ?string $configName,
        public string $package,
        public array $paramDescriptions = [],
        public bool $installHook = false,
    ) {}
}
