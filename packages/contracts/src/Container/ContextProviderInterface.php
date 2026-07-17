<?php

declare(strict_types=1);

/**
 * Returns the active `ContextInterface` for the current unit of execution:
 * the process under FPM, the current coroutine under Swoole, the current
 * fiber otherwise.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Contracts\Container;

interface ContextProviderInterface
{
    /**
     * The context bound to the current unit of execution.
     *
     * @return ContextInterface
     */
    public function getContext(): ContextInterface;
}
