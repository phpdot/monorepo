<?php

declare(strict_types=1);

/**
 * Swoole Context Provider
 *
 * Returns a SwooleContext when inside a coroutine,
 * or an ArrayContext fallback when outside (CLI, bootstrap).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Container\Swoole;

use PHPdot\Container\Context\ArrayContext;
use PHPdot\Contracts\Container\ContextInterface;
use PHPdot\Contracts\Container\ContextProviderInterface;
use Swoole\Coroutine;

final class SwooleContextProvider implements ContextProviderInterface
{
    private ArrayContext|null $fallback = null;

    /**
     * Get the context for the current execution unit.
     *
     * Inside a coroutine: returns a SwooleContext backed by Coroutine::getContext().
     * Outside a coroutine: returns a shared ArrayContext fallback.
     *
     * @return ContextInterface
     */
    public function getContext(): ContextInterface
    {
        if (Coroutine::getCid() > 0) {
            return new SwooleContext();
        }

        $this->fallback ??= new ArrayContext();

        return $this->fallback;
    }
}
