<?php

declare(strict_types=1);

/**
 * Definition Generator
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package\Generator;

use PHPdot\Container\Scope;
use PHPdot\Package\Scanner\PackageMeta;
use PHPdot\Package\Scanner\ScannedClass;

final class DefinitionGenerator
{
    /**
     * Generate the container definitions file from the scan result.
     *
     * @param list<ScannedClass> $classes
     * @param array<string, PackageMeta> $packages
     *
     * @return string
     */
    public function generate(array $classes, array $packages = []): string
    {
        $lines = [];
        $lines[] = "<?php\n";
        $lines[] = $this->generateHeader();
        $lines[] = "\ndeclare(strict_types=1);\n";
        $lines[] = "\nuse PHPdot\\Container\\Definition\\ScopedDefinition;";
        $lines[] = "\nuse PHPdot\\Container\\Scope;";
        $lines[] = "\nuse Psr\\Container\\ContainerInterface;\n";
        $lines[] = "\nreturn [\n";

        $grouped = $this->groupByPackage($classes);

        foreach ($grouped as $package => $group) {
            $meta = $packages[$package] ?? null;
            $lines[] = $this->generatePackageHeader($package, $meta);

            foreach ($group as $scanned) {
                if ($scanned->scope === null) {
                    continue;
                }

                $lines[] = $this->generateClass($scanned, $scanned->scope);

                foreach ($scanned->binds as $interface) {
                    $lines[] = $this->generateBinding($scanned, $scanned->scope, $interface);
                }
            }
        }

        $lines[] = $this->generateSelfBindings();
        $lines[] = "\n];\n";

        return implode('', $lines);
    }

    /**
     * Self-bindings for phpdot/package's own runtime services. Emitted on every
     * generation so apps can inject `PackageManager` and `Manifest` without
     * registering them manually. `Manifest` resolves through `PackageManager`,
     * falling back to an empty manifest when no manifest file has been written
     * yet (first install, before any rebuild).
     *
     * @return string
     */
    private function generateSelfBindings(): string
    {
        return <<<'PHP'

            /**
             * phpdot/package
             * Self-bindings — runtime access to the package manager and manifest.
             *
             * @see https://github.com/phpdot/package
             */

            \PHPdot\Package\PackageManager::class => new ScopedDefinition(
                scope: Scope::SINGLETON,
            ),

            \PHPdot\Package\Manifest::class => new ScopedDefinition(
                scope: Scope::SINGLETON,
                factory: static fn (ContainerInterface $c): \PHPdot\Package\Manifest
                    => $c->get(\PHPdot\Package\PackageManager::class)->manifest()
                        ?? new \PHPdot\Package\Manifest([], ''),
            ),

        PHP;
    }

    /**
     * Generate header.
     *
     * @return string
     */
    private function generateHeader(): string
    {
        $timestamp = date('c');

        return <<<PHP

            /**
             * PHPdot Container Definitions
             *
             * @generated   phpdot/package
             * @date        {$timestamp}
             * @see         https://github.com/phpdot/package
             *
             * Regenerated on every composer install/update/require/remove.
             * Do not edit — changes will be overwritten.
             */

            PHP;
    }

    /**
     * Generate package header.
     *
     * @param string $package
     * @param ?PackageMeta $meta
     *
     * @return string
     */
    private function generatePackageHeader(string $package, ?PackageMeta $meta): string
    {
        $lines = "\n    /**\n";
        $lines .= "     * {$package}\n";

        if ($meta !== null && $meta->description !== '') {
            $lines .= "     * {$meta->description}\n";
        }

        if ($meta !== null && $meta->url !== '') {
            $lines .= "     *\n";
            $lines .= "     * @see {$meta->url}\n";
        }

        $lines .= "     */\n";

        return $lines;
    }

    /**
     * Generate class.
     *
     * @param ScannedClass $scanned
     * @param Scope $classScope
     *
     * @return string
     */
    private function generateClass(ScannedClass $scanned, Scope $classScope): string
    {
        $fqcn = $this->fqcn($scanned->class);
        $scope = $this->scopeString($classScope);

        if ($scanned->configName !== null) {
            return <<<PHP

                    {$fqcn}::class => new ScopedDefinition(
                        scope: {$scope},
                        factory: static fn (ContainerInterface \$c): {$fqcn}
                            => \$c->get(\\PHPdot\\Config\\Configuration::class)->dto('{$scanned->configName}', {$fqcn}::class),
                    ),

                PHP;
        }

        if ($scanned->params === []) {
            return <<<PHP

                    {$fqcn}::class => new ScopedDefinition(
                        scope: {$scope},
                    ),

                PHP;
        }

        $gets = [];
        foreach ($scanned->params as $name => $param) {
            $gets[] = "            {$name}: \$c->get({$this->fqcn($param)}::class),";
        }
        $getLines = implode("\n", $gets);

        return <<<PHP

                {$fqcn}::class => new ScopedDefinition(
                    scope: {$scope},
                    factory: static fn (ContainerInterface \$c): {$fqcn}
                        => new {$fqcn}(
            {$getLines}
                        ),
                ),

            PHP;
    }

    /**
     * Generate binding.
     *
     * @param ScannedClass $scanned
     * @param Scope $classScope
     * @param string $interface
     *
     * @return string
     */
    private function generateBinding(ScannedClass $scanned, Scope $classScope, string $interface): string
    {
        $ifqcn = $this->fqcn($interface);
        $cfqcn = $this->fqcn($scanned->class);
        $scope = $this->scopeString($classScope);

        return <<<PHP

                {$ifqcn}::class => new ScopedDefinition(
                    scope: {$scope},
                    factory: static fn (ContainerInterface \$c): {$ifqcn}
                        => \$c->get({$cfqcn}::class),
                ),

            PHP;
    }

    /**
     * Scope string.
     *
     * @param Scope $scope
     *
     * @return string
     */
    private function scopeString(Scope $scope): string
    {
        return match ($scope) {
            Scope::SINGLETON => 'Scope::SINGLETON',
            Scope::SCOPED => 'Scope::SCOPED',
            Scope::TRANSIENT => 'Scope::TRANSIENT',
        };
    }

    /**
     * Fqcn.
     *
     * @param string $class
     *
     * @return string
     */
    private function fqcn(string $class): string
    {
        return '\\' . ltrim($class, '\\');
    }

    /**
     * Group scanned classes by their owning package.
     *
     * @param list<ScannedClass> $classes
     *
     * @return array<string, list<ScannedClass>>
     */
    private function groupByPackage(array $classes): array
    {
        $grouped = [];

        foreach ($classes as $scanned) {
            $grouped[$scanned->package][] = $scanned;
        }

        return $grouped;
    }
}
