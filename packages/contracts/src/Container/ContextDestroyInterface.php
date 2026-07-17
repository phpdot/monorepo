<?php

declare(strict_types=1);

/**
 * A context that supports per-context destroy callbacks.
 *
 * Optional capability extending `ContextInterface`: the DI container
 * feature-detects it with `instanceof`, and callbacks run when the current
 * context ends.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Contracts\Container;

use Closure;

interface ContextDestroyInterface
{
    /**
     * Register a callback to run when the current context is destroyed.
     *
     * Callbacks SHOULD be invoked in LIFO order. Exceptions from callbacks
     * MUST NOT propagate — destroy is best-effort cleanup.
     *
     * @param Closure(): void $callback
     *
     * @return void
     */
    public function onDestroy(Closure $callback): void;
}
