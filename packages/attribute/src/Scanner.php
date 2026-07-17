<?php

declare(strict_types=1);

/**
 * Facade of the package: discovers classes, scans their attributes
 * through reflection, and caches the resulting map.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Attribute;

use PHPdot\Attribute\Cache\FileCache;
use PHPdot\Attribute\Discovery\ClassDiscovery;
use PHPdot\Attribute\Discovery\TokenDiscovery;
use RuntimeException;

final class Scanner
{
    private readonly ClassDiscovery $discovery;

    private readonly ReflectionScanner $reflectionScanner;

    private ?Registry $registry = null;

    /**
     * Create a scanner; collaborators default to standard implementations.
     *
     * @param ?ClassDiscovery $discovery
     * @param ?ReflectionScanner $reflectionScanner
     * @param ?FileCache $cache
     */
    public function __construct(
        ?ClassDiscovery $discovery = null,
        ?ReflectionScanner $reflectionScanner = null,
        private readonly ?FileCache $cache = null,
    ) {
        $this->discovery = $discovery ?? new ClassDiscovery(tokenDiscovery: new TokenDiscovery());
        $this->reflectionScanner = $reflectionScanner ?? new ReflectionScanner();
    }

    /**
     * Drop the file cache and the memoized registry.
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->cache?->clear();
        $this->registry = null;
    }

    /**
     * The registry over the scanned attribute map, built on first use.
     *
     * @return Registry
     */
    public function registry(): Registry
    {
        if ($this->registry !== null) {
            return $this->registry;
        }

        throw new RuntimeException('Scanner has not been scanned yet. Call scan() or scanClasses() first.');
    }

    /**
     * Discover classes under the directories and scan their attributes, using the cache when valid.
     *
     * @param list<string> $directories
     * @param list<class-string> $filter
     * @param list<string> $namespaces
     * @param list<string> $excludePatterns
     * @param string $projectRoot
     * @param int $visibilityFilter
     * @param bool $forceRescan
     *
     * @return Registry
     */
    public function scan(
        array $directories,
        string $projectRoot = '',
        array $filter = [],
        array $namespaces = [],
        array $excludePatterns = [],
        int $visibilityFilter = 0,
        bool $forceRescan = false,
    ): Registry {
        if (!$forceRescan && $this->cache !== null && $this->cache->has()) {
            $map = $this->cache->read();

            if ($map !== null) {
                $this->registry = new Registry($map);

                return $this->registry;
            }
        }

        $classes = $this->discovery->discover(
            directories: $directories,
            projectRoot: $projectRoot,
            namespaces: $namespaces,
            excludePatterns: $excludePatterns,
        );

        return $this->buildRegistry($classes, $filter, $visibilityFilter, $directories);
    }

    /**
     * Scan an explicit list of classes, bypassing discovery.
     *
     * @param list<class-string> $classes
     * @param list<class-string> $filter
     * @param int $visibilityFilter
     * @param bool $forceRescan
     *
     * @return Registry
     */
    public function scanClasses(
        array $classes,
        array $filter = [],
        int $visibilityFilter = 0,
        bool $forceRescan = false,
    ): Registry {
        if (!$forceRescan && $this->cache !== null && $this->cache->has()) {
            $map = $this->cache->read();

            if ($map !== null) {
                $this->registry = new Registry($map);

                return $this->registry;
            }
        }

        return $this->buildRegistry($classes, $filter, $visibilityFilter, []);
    }

    /**
     * Wrap the scanned map in a registry and memoize it.
     *
     * @param list<class-string> $classes
     * @param list<class-string> $filter
     * @param list<string> $directories
     * @param int $visibilityFilter
     *
     * @return Registry
     */
    private function buildRegistry(
        array $classes,
        array $filter,
        int $visibilityFilter,
        array $directories,
    ): Registry {
        $map = $this->reflectionScanner->scan(
            classes: $classes,
            filter: $filter,
            visibilityFilter: $visibilityFilter,
            directories: $directories,
        );

        if ($this->cache !== null) {
            $this->cache->write($map);
        }

        $this->registry = new Registry($map);

        return $this->registry;
    }
}
