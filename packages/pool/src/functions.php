<?php

declare(strict_types=1);

/**
 * Pool wiring helper: a scoped definition over a named pool.
 *
 * Both functions are guarded: this file is files-autoloaded and also reachable
 * through the PSR-4 autoloader by path-derived class lookups (phpdot/package's
 * scanner), so a second include must stay silent — the first definition wins.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Pool;

use PHPdot\Container\Definition\ScopedDefinition;

use function PHPdot\Container\scoped;

use PHPdot\Contracts\Pool\ConnectorInterface;
use PHPdot\Pool\Exception\PoolException;
use Psr\Container\ContainerInterface;

if (!function_exists('PHPdot\\Pool\\pooled')) {
    /**
     * Bind a connection class to a named pool as a scoped definition: the first
     * resolution inside a coroutine borrows from the pool, every later resolution
     * in the same coroutine returns the same connection, and the context end
     * releases it — the container's own scoping doing the lifecycle. Outside a
     * coroutine the same definition hands a dedicated, unpooled connection.
     *
     * @param string $name The pool name: a section, or "section.pool" for a
     *                     multi-pool client's `pools` sub-block
     * @param class-string<ConnectorInterface> $connector The client package's connector
     */
    function pooled(string $name, string $connector): ScopedDefinition
    {
        return scoped(
            static fn(ContainerInterface $container): object => registryOf($container)
                ->connection($name, $connector),
            static function (object $connection, ContainerInterface $container) use ($name, $connector): void {
                registryOf($container)->release($name, $connector, $connection);
            },
        );
    }
}

if (!function_exists('PHPdot\\Pool\\registryOf')) {
    /**
     * The registry behind a container entry, narrowed from the PSR-11 `mixed`.
     *
     * @param ContainerInterface $container The container the definition resolves through
     *
     * @throws PoolException When the entry is not the registry
     */
    function registryOf(ContainerInterface $container): PoolRegistry
    {
        $registry = $container->get(PoolRegistry::class);

        if (!$registry instanceof PoolRegistry) {
            throw new PoolException('The ' . PoolRegistry::class . ' container entry is not the pool registry');
        }

        return $registry;
    }
}
