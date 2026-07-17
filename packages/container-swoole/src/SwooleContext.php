<?php

declare(strict_types=1);

/**
 * Swoole Context
 *
 * Per-coroutine storage backed by Swoole\Coroutine::getContext().
 * Each coroutine gets its own isolated ArrayObject, automatically
 * destroyed by Swoole when the coroutine exits.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Container\Swoole;

use ArrayObject;
use Closure;
use PHPdot\Contracts\Container\ContextDestroyInterface;
use PHPdot\Contracts\Container\ContextInterface;
use RuntimeException;
use Swoole\Coroutine;

final class SwooleContext implements ContextInterface, ContextDestroyInterface
{
    /**
     * Get the current coroutine's context ArrayObject.
     *
     * @return ArrayObject<string, object>
     */
    private function context(): ArrayObject
    {
        $ctx = Coroutine::getContext();

        if (!$ctx instanceof ArrayObject) {
            throw new RuntimeException('Swoole coroutine context is not available.');
        }

        return $ctx;
    }

    /**
     * Check if a service exists in the current coroutine's context.
     *
     * @param string $id
     *
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->context()[$id]);
    }

    /**
     * Get a service from the current coroutine's context.
     *
     * @param string $id
     *
     * @return ?object
     */
    public function get(string $id): object|null
    {
        /**
         * @var object|null
         */
        return $this->context()[$id] ?? null;
    }

    /**
     * Store a service in the current coroutine's context.
     *
     * @param object $instance
     * @param string $id
     *
     * @return void
     */
    public function set(string $id, object $instance): void
    {
        $this->context()[$id] = $instance;
    }

    /**
     * Remove a service from the current coroutine's context.
     *
     * @param string $id
     *
     * @return void
     */
    public function unset(string $id): void
    {
        unset($this->context()[$id]);
    }

    /**
     * Register a callback to fire at the end of the current coroutine.
     *
     * Delegates to Swoole's Coroutine::defer, which invokes registered
     * callbacks in LIFO order when the coroutine exits.
     *
     * @param Closure(): void $callback
     *
     * @return void
     */
    public function onDestroy(Closure $callback): void
    {
        Coroutine::defer($callback);
    }

    /**
     * Clear all services from the current coroutine's context.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->context()->exchangeArray([]);
    }
}
