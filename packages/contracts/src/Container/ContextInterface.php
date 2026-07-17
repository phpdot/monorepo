<?php

declare(strict_types=1);

/**
 * The active execution context — a typed key/value store of object instances
 * scoped to the current unit of execution (process, coroutine, or fiber).
 * Used by the DI container's `Scoped` lifecycle to isolate per-execution
 * service instances.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Contracts\Container;

interface ContextInterface
{
    /**
     * Whether an instance is stored under the given id in this context.
     *
     * @param string $id
     *
     * @return bool
     */
    public function has(string $id): bool;

    /**
     * The stored instance, or null when nothing is stored under the id.
     *
     * @param string $id
     *
     * @return ?object
     */
    public function get(string $id): object|null;

    /**
     * Store an instance under the given id in this context.
     *
     * @param object $instance
     * @param string $id
     *
     * @return void
     */
    public function set(string $id, object $instance): void;

    /**
     * Remove the instance stored under the given id.
     *
     * @param string $id
     *
     * @return void
     */
    public function unset(string $id): void;

    /**
     * Drop every instance stored in this context.
     *
     * @return void
     */
    public function reset(): void;
}
